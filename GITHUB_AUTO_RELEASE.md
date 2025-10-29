# 🚀 GitHub Auto-Release Setup

## 📋 TỔNG QUAN

Mỗi khi commit code lên GitHub (branch `main` hoặc `master`), GitHub Actions sẽ tự động:
1. ✅ Tạo file `latest-update.zip` 
2. ✅ Upload lên GitHub Release với tag `latest`
3. ✅ Servers có thể auto-update từ release này

## 🔧 SETUP

### Bước 1: Push code lên GitHub

```bash
# Khởi tạo git (nếu chưa có)
git init
git add .
git commit -m "Initial commit with auto-release"

# Thêm remote repository
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO.git

# Push lên GitHub
git branch -M main
git push -u origin main
```

### Bước 2: Cấu hình .env

Thêm vào file `.env`:

```env
# GitHub Auto-Update URL
UPDATE_URL=https://github.com/YOUR_USERNAME/YOUR_REPO/releases/download/latest/latest-update.zip
```

Thay `YOUR_USERNAME` và `YOUR_REPO` bằng thông tin thực tế:
- **YOUR_USERNAME**: GitHub username của bạn
- **YOUR_REPO**: Tên repository (ví dụ: `gpm-mysql-backup`)

**Ví dụ:**
```env
UPDATE_URL=https://github.com/johndoe/gpm-mysql-backup/releases/download/latest/latest-update.zip
```

### Bước 3: GitHub Actions sẽ tự động chạy

Sau khi push:
1. Vào GitHub repository
2. Click tab **Actions**
3. Sẽ thấy workflow "Auto Release" đang chạy
4. Sau vài phút → Hoàn thành ✅
5. Vào tab **Releases** → Thấy release "Latest Update"

## 📦 NỘI DUNG PACKAGE

File `latest-update.zip` bao gồm:

### ✅ Có trong package:
- `app/` - Controllers, Models, Services
- `config/` - Configuration files
- `database/` - Migrations
- `public/` - Assets, index.php
- `resources/` - Views, CSS, JS
- `routes/` - Route definitions
- `bootstrap/` - Laravel bootstrap
- `storage/` - Empty structure (logs excluded)
- `composer.json`, `composer.lock`
- `artisan`
- `.htaccess`

### ❌ KHÔNG có trong package:
- `.env` (phải config thủ công trên mỗi server)
- `vendor/` (chạy `composer install`)
- `node_modules/` (chạy `npm install`)
- `storage/logs/*` (logs hiện tại)
- `.git/` (git history)

## 🔄 WORKFLOW

```
┌─────────────────┐
│  Commit & Push  │
│   to GitHub     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ GitHub Actions  │
│   Triggered     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Create ZIP:    │
│ - Exclude .git  │
│ - Exclude vendor│
│ - Exclude .env  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Delete old      │
│ "latest" tag    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Create Release: │
│ - Tag: latest   │
│ - Upload ZIP    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Servers can    │
│  auto-update!   │
└─────────────────┘
```

## 🎯 SỬ DỤNG AUTO-UPDATE

### Trên Server Production:

1. **Qua Admin Panel:**
   - Login: `https://domain.com/admin`
   - Click button: "Update private server"
   - Tự động download + extract + migrate
   - Done! ✅

2. **Qua API:**
   ```bash
   curl -X GET "https://domain.com/auto-update" \
     -H "Authorization: Bearer YOUR_SANCTUM_TOKEN"
   ```

3. **Thủ công:**
   ```bash
   # Download
   wget https://github.com/YOUR_USERNAME/YOUR_REPO/releases/download/latest/latest-update.zip
   
   # Extract
   unzip -o latest-update.zip -d /var/www/html
   
   # Permissions
   chmod -R 755 storage bootstrap/cache
   chmod -R 777 storage/logs
   
   # Cache
   php artisan config:cache
   php artisan route:cache
   ```

## ⚙️ WORKFLOW FILE

File `.github/workflows/auto-release.yml` đã được tạo tự động.

### Customize workflow:

**Thay đổi branch trigger:**
```yaml
on:
  push:
    branches:
      - main        # Hoặc master, develop, production...
```

**Thay đổi files exclude:**
```yaml
rsync -av \
  --exclude='your-custom-folder' \
  --exclude='*.log' \
  ./ release-temp/
```

## 🔍 KIỂM TRA

### Check Release trên GitHub:

1. Vào: `https://github.com/YOUR_USERNAME/YOUR_REPO/releases`
2. Thấy release "Latest Update"
3. Download link: `latest-update.zip`

### Test Auto-Update:

```bash
# Check URL accessible
curl -I https://github.com/YOUR_USERNAME/YOUR_REPO/releases/download/latest/latest-update.zip

# Should return: HTTP/2 302 (redirect to actual file)
```

### Check workflow logs:

1. GitHub → Actions tab
2. Click vào workflow run
3. Xem logs từng step

## 🐛 TROUBLESHOOTING

### 1. Workflow không chạy

**Nguyên nhân:** Branch không phải `main` hoặc `master`

**Fix:**
```yaml
# Sửa trong .github/workflows/auto-release.yml
on:
  push:
    branches:
      - YOUR_BRANCH_NAME
```

### 2. Permission denied khi create release

**Nguyên nhân:** GitHub token không có quyền

**Fix:**
1. GitHub → Settings → Actions → General
2. Workflow permissions → "Read and write permissions"
3. Save

### 3. ZIP file quá lớn

**Nguyên nhân:** Có nhiều files không cần thiết

**Fix:** Thêm exclude trong workflow:
```yaml
--exclude='public/uploads/*' \
--exclude='storage/app/backups/*' \
```

### 4. Auto-update fail trên server

**Check:**
```bash
# 1. Check URL in .env
grep UPDATE_URL .env

# 2. Test download manually
wget $(grep UPDATE_URL .env | cut -d'=' -f2)

# 3. Check permissions
ls -la storage/app/
```

## 📝 VERSION TRACKING

Current version in code:
```php
// app/Http/Controllers/Api/SettingController.php
public static $server_version = 12;
```

Sau mỗi update quan trọng, tăng version:
```php
public static $server_version = 13; // New version
```

## 🔐 SECURITY

### Protected files:

1. `.env` - KHÔNG bao giờ commit
2. `storage/logs/` - Logs riêng tư
3. Database credentials - Chỉ trên server

### GitHub Secrets:

Nếu cần thêm secrets:
1. GitHub → Settings → Secrets and variables → Actions
2. New repository secret
3. Use in workflow:
   ```yaml
   env:
     MY_SECRET: ${{ secrets.MY_SECRET }}
   ```

## 🎉 HOÀN THÀNH!

Sau khi setup xong:

1. ✅ Mỗi commit → Auto create release
2. ✅ Servers tự động update từ GitHub
3. ✅ Không cần manual upload files
4. ✅ Versioning tự động
5. ✅ Release notes tự động generate

---

**Next Steps:**
1. Push code lên GitHub
2. Config UPDATE_URL trong .env
3. Test auto-update trên 1 server
4. Roll out to all servers

**Happy Coding! 🚀**

