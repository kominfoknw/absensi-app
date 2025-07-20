from flask import Flask, request, jsonify
import cv2
import numpy as np
import face_recognition
import base64
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

def read_base64_image(base64_string):
    try:
        decoded_data = base64.b64decode(base64_string)
        np_data = np.frombuffer(decoded_data, np.uint8)
        img = cv2.imdecode(np_data, cv2.IMREAD_COLOR)
        return img
    except Exception as e:
        print("⚠️ Gagal decode base64 image:", e)
        return None

@app.route('/verify-face', methods=['POST'])
def verify_face():
    try:
        data = request.get_json()
        if not data:
            print("❌ JSON tidak ditemukan")
            return jsonify({'match': False, 'error': 'Invalid JSON'}), 400

        img_base64_captured = data.get('image_base64')
        img_base64_db = data.get('db_image_base64')

        if not img_base64_captured or not img_base64_db:
            print("❌ Base64 data tidak lengkap")
            return jsonify({'match': False, 'error': 'Base64 image tidak lengkap'}), 400

        print("📦 Captured Image Length:", len(img_base64_captured))
        print("📦 DB Image Length:", len(img_base64_db))

        # Decode
        img_captured = read_base64_image(img_base64_captured)
        img_db = read_base64_image(img_base64_db)

        if img_captured is None or img_db is None:
            print("❌ Salah satu gambar gagal didecode")
            return jsonify({'match': False, 'error': 'Gagal decode salah satu gambar'}), 400

        # Lokasi wajah
        captured_locations = face_recognition.face_locations(img_captured)
        db_locations = face_recognition.face_locations(img_db)

        print(f"🔍 Face locations (captured): {captured_locations}")
        print(f"🔍 Face locations (db): {db_locations}")

        # Encode
        captured_encodings = face_recognition.face_encodings(img_captured, known_face_locations=captured_locations)
        db_encodings = face_recognition.face_encodings(img_db, known_face_locations=db_locations)

        if not captured_encodings:
            print("❌ Tidak ada wajah di gambar 'captured'")
            return jsonify({'match': False, 'error': 'Wajah tidak terdeteksi di foto absen'}), 400
        if not db_encodings:
            print("❌ Tidak ada wajah di gambar 'db'")
            return jsonify({'match': False, 'error': 'Wajah tidak terdeteksi di foto database'}), 400

        # Compare wajah
        face_distance = face_recognition.face_distance([db_encodings[0]], captured_encodings[0])[0]
        threshold = 0.55  # bisa disesuaikan (default face_recognition = 0.6)
        match = bool(face_distance < threshold)

        print(f"📏 Face distance: {face_distance}")
        print(f"✅ Face match result: {match}")

        return jsonify({
            'match': match,
            'distance': face_distance,
            'threshold': threshold
        }), 200

    except Exception as e:
        print("🔥 ERROR (Global catch):", str(e))
        return jsonify({
    'match': False,
    'error': 'Internal server error',
    'details': str(e)  # pisahkan detail jika ingin ditampilkan
}), 500

if __name__ == '__main__':
    print("🚀 Face Recognition API running on http://0.0.0.0:8001")
    app.run(host='0.0.0.0', port=8001)
