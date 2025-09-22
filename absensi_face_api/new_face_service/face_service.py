from flask import Flask, request, jsonify
from flask_cors import CORS
import base64
import numpy as np
import cv2
from deepface import DeepFace

app = Flask(__name__)
CORS(app)

def read_base64_image(base64_string):
    try:
        decoded_data = base64.b64decode(base64_string)
        np_data = np.frombuffer(decoded_data, np.uint8)
        img = cv2.imdecode(np_data, cv2.IMREAD_COLOR)
        return img
    except:
        return None

@app.route('/verify-face', methods=['POST'])
def verify_face():
    data = request.get_json()
    if not data or 'image_base64' not in data or 'db_image_base64' not in data:
        return jsonify({'match': False, 'error': 'Base64 image tidak lengkap'}), 400

    img_captured = read_base64_image(data['image_base64'])
    img_db = read_base64_image(data['db_image_base64'])
    if img_captured is None or img_db is None:
        return jsonify({'match': False, 'error': 'Gagal decode gambar'}), 400

    try:
        result = DeepFace.verify(img_captured, img_db, enforce_detection=False)
        return jsonify({
            'match': result['verified'],
            'distance': result['distance'],
            'threshold': result['threshold']
        })
    except Exception as e:
        return jsonify({'match': False, 'error': str(e)}), 500

if __name__ == "__main__":
    app.run(host='0.0.0.0', port=8001)
