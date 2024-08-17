#!/bin/bash
# Yêu cầu người dùng nhập port
read -p "Nhập port cho ứng dụng (mặc định 80): " app_port
app_port=${app_port:-80}
# Cập nhật hoặc thêm các cấu hình port vào .env
sed -i "/^APP_PORT=/c\APP_PORT=$app_port" .env
rep -qxF 'APP_PORT=' .env || echo "APP_PORT=$app_port" >> .env

echo "Chọn chế độ lưu trữ:"
echo "1) Lưu tại hosting"
echo "2) Lưu tại S3"
read -p "Lựa chọn của bạn: " storage_choice

if [ "$storage_choice" == "2" ]; then
    read -p "Nhập S3 API Key: " s3_key
    read -p "Nhập S3 API Secret: " s3_secret
    read -p "Nhập S3 Bucket: " s3_bucket
    read -p "Nhập S3 Region: " s3_region

    sed -i "/^S3_KEY=/c\S3_KEY=$s3_key" .env
    sed -i "/^S3_SECRET=/c\S3_SECRET=$s3_secret" .env
    sed -i "/^S3_BUCKET=/c\S3_BUCKET=$s3_bucket" .env
    sed -i "/^S3_REGION=/c\S3_REGION=$s3_region" .env

    # Nếu biến không tồn tại, thêm chúng vào file .env
    grep -qxF 'S3_KEY=' .env || echo "S3_KEY=$s3_key" >> .env
    grep -qxF 'S3_SECRET=' .env || echo "S3_SECRET=$s3_secret" >> .env
    grep -qxF 'S3_BUCKET=' .env || echo "S3_BUCKET=$s3_bucket" >> .env
    grep -qxF 'S3_REGION=' .env || echo "S3_REGION=$s3_region" >> .env

    sed -i "s/FILESYSTEM_DRIVER=local/FILESYSTEM_DRIVER=s3/g" .env

elif [ "$storage_choice" == "1" ]; then
    docker exec -i $(docker-compose ps -q db) mysql -u root -psecret gpmlogin-db <<EOF
    INSERT INTO settings (name, value) VALUES ('storage_type', 'hosting')
    ON DUPLICATE KEY UPDATE value='hosting';
EOF
fi

apache2-foreground
