const toast = document.getElementById("toast");

async function pollNotifications() {
  if (!toast) return;

  try {
    const response = await fetch("index.php?route=api/notifications", { cache: "no-store" });
    if (!response.ok) return;

    const alerts = await response.json();
    const latest = alerts[0];
    if (!latest) {
      toast.hidden = true;
      return;
    }

    toast.hidden = false;
    toast.innerHTML = `<strong>${latest.type.toUpperCase()}</strong><span>${latest.message}</span><small>${latest.camera_name}</small>`;
  } catch (error) {
    console.warn("No se pudo consultar notificaciones", error);
  }
}

pollNotifications();
setInterval(pollNotifications, 10000);

document.querySelectorAll("[data-camera-preview]").forEach((preview) => {
  const video = preview.querySelector("[data-camera-video]");
  const select = preview.querySelector("[data-camera-select]");
  const startButton = preview.querySelector("[data-camera-start]");
  const stopButton = preview.querySelector("[data-camera-stop]");
  const message = preview.querySelector("[data-camera-message]");
  const empty = preview.querySelector("[data-camera-empty]");
  let stream = null;

  async function loadDevices() {
    if (!navigator.mediaDevices?.getUserMedia) {
      message.textContent = "Tu navegador no permite vista previa de camara.";
      startButton.disabled = true;
      return;
    }

    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const cameras = devices.filter((device) => device.kind === "videoinput");
      select.innerHTML = "";

      cameras.forEach((camera, index) => {
        const option = document.createElement("option");
        option.value = camera.deviceId;
        option.textContent = camera.label || `Camara ${index + 1}`;
        select.appendChild(option);
      });

      if (!cameras.length) {
        const option = document.createElement("option");
        option.textContent = "Camara predeterminada";
        option.value = "";
        select.appendChild(option);
      }
    } catch (error) {
      message.textContent = "No se pudo listar camaras. Intenta iniciar la vista previa.";
    }
  }

  function stopPreview() {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
    }
    stream = null;
    video.srcObject = null;
    empty.hidden = false;
    startButton.disabled = false;
    stopButton.disabled = true;
    message.textContent = "Vista previa detenida.";
  }

  async function startPreview() {
    stopPreview();

    try {
      const deviceId = select.value;
      stream = await navigator.mediaDevices.getUserMedia({
        video: deviceId ? { deviceId: { exact: deviceId } } : true,
        audio: false,
      });
      video.srcObject = stream;
      empty.hidden = true;
      startButton.disabled = true;
      stopButton.disabled = false;
      message.textContent = "Camara activa en tiempo real.";
      await loadDevices();
    } catch (error) {
      message.textContent = "No se pudo abrir la camara. Revisa permisos o si otra app la esta usando.";
    }
  }

  startButton.addEventListener("click", startPreview);
  stopButton.addEventListener("click", stopPreview);
  loadDevices();
});

document.querySelectorAll("[data-person-capture]").forEach((capture) => {
  const video = capture.querySelector("[data-person-video]");
  const canvas = capture.querySelector("[data-person-canvas]");
  const preview = capture.querySelector("[data-person-photo-preview]");
  const startButton = capture.querySelector("[data-person-start]");
  const shotButton = capture.querySelector("[data-person-shot]");
  const message = capture.querySelector("[data-person-message]");
  const input = document.querySelector("[data-person-captured-photo]");
  let stream = null;

  async function startPersonCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      video.srcObject = stream;
      video.hidden = false;
      preview.hidden = true;
      shotButton.disabled = false;
      message.textContent = "Camara lista. Mira de frente y captura la photo.";
    } catch (error) {
      message.textContent = "No se pudo abrir la webcam para registrar la photo.";
    }
  }

  function capturePhoto() {
    if (!stream) return;

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 360;
    canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL("image/jpeg", 0.9);
    input.value = dataUrl;
    preview.src = dataUrl;
    preview.hidden = false;
    video.hidden = true;
    message.textContent = "Photo capturada. Puedes registrar la persona.";
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
    shotButton.disabled = true;
  }

  startButton.addEventListener("click", startPersonCamera);
  shotButton.addEventListener("click", capturePhoto);
});
