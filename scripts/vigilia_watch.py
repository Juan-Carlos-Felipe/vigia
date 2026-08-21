"""VIGILIA 24/7 camera watcher powered by the local Ultralytics YOLO API."""

from __future__ import annotations

import argparse
import os
import threading
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Any

import cv2
import mysql.connector

from ultralytics import YOLO

ROOT = Path(__file__).resolve().parents[1]
SNAPSHOT_DIR = ROOT / "vigilia" / "storage" / "snapshots"
FACE_CASCADE = cv2.CascadeClassifier(str(Path(cv2.data.haarcascades) / "haarcascade_frontalface_default.xml"))


@dataclass
class PersonBox:
    xyxy: tuple[int, int, int, int]
    confidence: float
    center: tuple[int, int]


@dataclass
class CameraState:
    last_alert_at: dict[str, float] = field(default_factory=dict)
    last_sighting_at: dict[int, float] = field(default_factory=dict)
    last_heartbeat_at: float = 0
    previous_centers: list[tuple[int, int]] = field(default_factory=list)


@dataclass
class KnownPerson:
    person_id: int
    name: str
    face: Any


def db_config() -> dict[str, Any]:
    return {
        "host": os.getenv("VIGILIA_DB_HOST", "127.0.0.1"),
        "database": os.getenv("VIGILIA_DB_NAME", "vigilia"),
        "user": os.getenv("VIGILIA_DB_USER", "root"),
        "password": os.getenv("VIGILIA_DB_PASS", ""),
    }


def connect_db():
    return mysql.connector.connect(**db_config())


def active_cameras() -> list[dict[str, Any]]:
    with connect_db() as conn:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id, name, source, location FROM cameras WHERE active = 1 ORDER BY id")
        return cursor.fetchall()


def mark_heartbeat(camera_id: int) -> None:
    with connect_db() as conn:
        cursor = conn.cursor()
        cursor.execute("UPDATE cameras SET last_heartbeat = NOW() WHERE id = %s", (camera_id,))
        conn.commit()


def relative_path(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def load_known_people() -> list[KnownPerson]:
    people: list[KnownPerson] = []
    with connect_db() as conn:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id, full_name, photo_path FROM people WHERE photo_path IS NOT NULL")

        for row in cursor.fetchall():
            photo_path = ROOT / str(row["photo_path"]).replace("/", os.sep)
            image = cv2.imread(str(photo_path))
            if image is None:
                continue

            face = extract_face(image)
            if face is not None:
                people.append(KnownPerson(int(row["id"]), str(row["full_name"]), face))

    return people


def extract_face(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    faces = FACE_CASCADE.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(50, 50))
    if len(faces) > 0:
        x, y, w, h = max(faces, key=lambda face: face[2] * face[3])
        gray = gray[y : y + h, x : x + w]

    gray = cv2.resize(gray, (96, 96))
    gray = cv2.equalizeHist(gray)
    return gray


def face_similarity(first, second) -> float:
    diff = cv2.absdiff(first, second)
    return max(0.0, 1.0 - float(diff.mean()) / 255.0)


def identify_person(
    frame, box: PersonBox, known_people: list[KnownPerson], threshold: float
) -> tuple[KnownPerson, float] | None:
    if not known_people:
        return None

    x1, y1, x2, y2 = box.xyxy
    crop = frame[max(0, y1) : max(0, y2), max(0, x1) : max(0, x2)]
    if crop.size == 0:
        return None

    face = extract_face(crop)
    if face is None:
        return None

    matches = [(person, face_similarity(face, person.face)) for person in known_people]
    person, score = max(matches, key=lambda match: match[1])
    if score >= threshold:
        return person, score

    return None


def can_save_sighting(state: CameraState, person_id: int, cooldown: int) -> bool:
    now = time.time()
    last = state.last_sighting_at.get(person_id, 0)
    if now - last < cooldown:
        return False

    state.last_sighting_at[person_id] = now
    return True


def save_sighting(camera: dict[str, Any], person: KnownPerson, confidence: float, frame) -> None:
    SNAPSHOT_DIR.mkdir(parents=True, exist_ok=True)
    filename = f"{datetime.now():%Y%m%d_%H%M%S}_{camera['id']}_person_{person.person_id}.jpg"
    snapshot = SNAPSHOT_DIR / filename
    cv2.imwrite(str(snapshot), frame)

    with connect_db() as conn:
        cursor = conn.cursor()
        cursor.execute(
            """
            INSERT INTO sightings (person_id, camera_id, camera_name, confidence, snapshot_path)
            VALUES (%s, %s, %s, %s, %s)
            """,
            (person.person_id, camera["id"], camera["name"], round(confidence * 100, 2), relative_path(snapshot)),
        )
        conn.commit()


def save_incident(
    camera: dict[str, Any], event_type: str, severity: str, message: str, confidence: float, frame
) -> None:
    SNAPSHOT_DIR.mkdir(parents=True, exist_ok=True)
    filename = f"{datetime.now():%Y%m%d_%H%M%S}_{camera['id']}_{event_type}.jpg"
    snapshot = SNAPSHOT_DIR / filename
    cv2.imwrite(str(snapshot), frame)

    relative_snapshot = relative_path(snapshot)
    with connect_db() as conn:
        cursor = conn.cursor()
        cursor.execute(
            """
            INSERT INTO incidents (camera_id, camera_name, type, severity, message, confidence, snapshot_path)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            """,
            (
                camera["id"],
                camera["name"],
                event_type,
                severity,
                message,
                round(confidence * 100, 2),
                relative_snapshot,
            ),
        )
        conn.commit()


def parse_source(source: str) -> int | str:
    source = source.strip()
    return int(source) if source.isdigit() else source


def person_boxes(result) -> list[PersonBox]:
    boxes: list[PersonBox] = []
    names = result.names

    for box in result.boxes:
        cls_id = int(box.cls[0])
        if names.get(cls_id) != "person":
            continue

        x1, y1, x2, y2 = [int(value) for value in box.xyxy[0].tolist()]
        center = ((x1 + x2) // 2, (y1 + y2) // 2)
        boxes.append(PersonBox((x1, y1, x2, y2), float(box.conf[0]), center))

    return boxes


def is_fall(box: PersonBox) -> bool:
    x1, y1, x2, y2 = box.xyxy
    width = max(1, x2 - x1)
    height = max(1, y2 - y1)
    return width / height > 1.35 and box.confidence >= 0.45


def motion_score(current: list[tuple[int, int]], previous: list[tuple[int, int]]) -> float:
    if not current or not previous:
        return 0.0

    distances = []
    for cx, cy in current:
        nearest = min(((cx - px) ** 2 + (cy - py) ** 2) ** 0.5 for px, py in previous)
        distances.append(nearest)
    return sum(distances) / len(distances)


def is_fight(boxes: list[PersonBox], state: CameraState) -> bool:
    if len(boxes) < 2:
        return False

    close_pairs = 0
    for index, first in enumerate(boxes):
        for second in boxes[index + 1 :]:
            distance = ((first.center[0] - second.center[0]) ** 2 + (first.center[1] - second.center[1]) ** 2) ** 0.5
            if distance < 95:
                close_pairs += 1

    movement = motion_score([box.center for box in boxes], state.previous_centers)
    return close_pairs > 0 and movement > 22


def can_alert(state: CameraState, event_type: str, cooldown: int) -> bool:
    now = time.time()
    last = state.last_alert_at.get(event_type, 0)
    if now - last < cooldown:
        return False

    state.last_alert_at[event_type] = now
    return True


def watch_camera(camera: dict[str, Any], args: argparse.Namespace) -> None:
    model = YOLO(args.model)
    source = parse_source(str(camera["source"]))
    capture = cv2.VideoCapture(source)
    state = CameraState()
    frame_index = 0
    known_people: list[KnownPerson] = []
    last_people_load_at = 0.0

    if not capture.isOpened():
        print(f"No se pudo abrir {camera['name']} ({camera['source']})")
        return

    print(f"Vigilando {camera['name']} desde {camera['source']}")

    while True:
        ok, frame = capture.read()
        if not ok:
            print(f"Reconectando {camera['name']}...")
            capture.release()
            time.sleep(args.reconnect_delay)
            capture = cv2.VideoCapture(source)
            continue

        frame_index += 1
        if frame_index % args.frame_stride != 0:
            continue

        results = model.predict(frame, classes=[0], conf=args.confidence, verbose=False)
        boxes = person_boxes(results[0])
        if time.time() - last_people_load_at > args.people_reload:
            known_people = load_known_people()
            last_people_load_at = time.time()

        if time.time() - state.last_heartbeat_at > 10:
            mark_heartbeat(int(camera["id"]))
            state.last_heartbeat_at = time.time()

        for box in boxes:
            match = identify_person(frame, box, known_people, args.identity_threshold)
            if match is None:
                continue

            person, score = match
            if can_save_sighting(state, person.person_id, args.identity_cooldown):
                save_sighting(camera, person, score, frame)
                print(f"Identificado {person.name} en {camera['name']} ({score:.2%})")

        if any(is_fall(box) for box in boxes) and can_alert(state, "fall", args.cooldown):
            save_incident(
                camera,
                "fall",
                "critical",
                "Possible caida detectada. Revisar de inmediato.",
                max((box.confidence for box in boxes), default=0),
                frame,
            )

        if is_fight(boxes, state) and can_alert(state, "fight", args.cooldown):
            save_incident(
                camera,
                "fight",
                "high",
                "Possible pelea o forcejeo entre personas detectado.",
                max((box.confidence for box in boxes), default=0),
                frame,
            )

        state.previous_centers = [box.center for box in boxes]
        time.sleep(args.sleep)


def main() -> None:
    parser = argparse.ArgumentParser(description="VIGILIA YOLO watcher")
    parser.add_argument("--model", default=os.getenv("VIGILIA_YOLO_MODEL", "yolo26n.pt"))
    parser.add_argument("--confidence", type=float, default=0.35)
    parser.add_argument("--frame-stride", type=int, default=3)
    parser.add_argument("--cooldown", type=int, default=45)
    parser.add_argument("--sleep", type=float, default=0.01)
    parser.add_argument("--reconnect-delay", type=int, default=5)
    parser.add_argument("--people-reload", type=int, default=30)
    parser.add_argument("--identity-threshold", type=float, default=0.72)
    parser.add_argument("--identity-cooldown", type=int, default=60)
    args = parser.parse_args()

    threads: dict[int, threading.Thread] = {}

    while True:
        cameras = active_cameras()
        if not cameras:
            print("No hay camaras activas. Esperando...")
            time.sleep(10)
            continue

        for camera in cameras:
            camera_id = int(camera["id"])
            thread = threads.get(camera_id)
            if thread and thread.is_alive():
                continue

            thread = threading.Thread(target=watch_camera, args=(camera, args), daemon=True)
            thread.start()
            threads[camera_id] = thread

        time.sleep(10)


if __name__ == "__main__":
    main()
