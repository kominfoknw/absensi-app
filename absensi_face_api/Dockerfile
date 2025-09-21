FROM python:3.10-slim

ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    cmake \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libopencv-dev \
    libatlas-base-dev \
    libboost-python-dev \
    libdlib-dev \
    python3-dev \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy project
COPY . .

# Install Python packages
RUN pip install --no-cache-dir -r requirements.txt

# Gunicorn lebih stabil daripada flask run
CMD ["gunicorn", "--bind", "0.0.0.0:8001", "face_service:app", "--workers", "2", "--threads", "4"]
