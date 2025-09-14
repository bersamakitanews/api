# Gunakan image PHP resmi
FROM php:8.1-cli

# Set working directory
WORKDIR /app

# Copy semua file ke container
COPY . .

# Jalankan PHP built-in server di port 10000
CMD ["php", "-S", "0.0.0.0:10000", "wm.php"]
