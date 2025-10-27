<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin site v12.2023</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>

    <style>
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 15px;
        }

        input {
            display: block;
            width: 100%;
        }

        a {
            text-decoration: none;
        }

        select {
            max-width: 300px !important;
        }

        .btn {
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h3 style="color: #0080C0">Admin site <small>(v{{ \App\Http\Controllers\Api\SettingController::$server_version }})</small></h3>
        @if (Session::has('msg'))
        <div class="alert alert-success">
            {{ Session::get('msg')}}
        </div>
        @endif
        <a class="badge bg-danger" href="{{ url('admin/auth/logout') }}">Logout</a>
        &nbsp;<a href="{{ url('admin/reset-profile-status') }}" class="badge bg-success">Reset profile status</a>
        &nbsp;<a href="{{ url('admin/migration') }}" class="badge bg-secondary">Migration database</a>
        &nbsp;<a href="{{ url('auto-update') }}" class="badge bg-secondary">Update private server</a>
        <br /><br /><br />

        <h3 style="color: #0080C0">Storage setting</h3><br />
        <form action="admin/save-setting">
            <select name="type" class="form-control mb-3" onchange="handleStorageTypeChange(this)">
                <option value="s3" @if ($storageType=='s3' ) selected @endif>S3 (setting api in .env file)</option>
                <option value="hosting" @if ($storageType=='hosting' ) selected @endif>Hosting (Recommended for LAN)</option>
            </select>

            <!-- S3 config -->
            <div id="s3Config" @if($storageType !='s3' ) style="display: none;" @endif>
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_KEY">S3_KEY</label>
                        <input name="S3_KEY" class="form-control" id="S3_KEY" rows="3" placeholder="S3 key" value="{{ $s3Config->S3_KEY  }}" />
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_PASSWORD">S3_PASSWORD</label>
                        <input name="S3_PASSWORD" class="form-control" id="S3_PASSWORD" rows="3" placeholder="S3 secret" value="{{ $s3Config->S3_PASSWORD  }}" />
                    </div>
                </div>
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_BUCKET">S3_BUCKET</label>
                        <input name="S3_BUCKET" class="form-control" id="S3_BUCKET" rows="3" placeholder="S3 bucket" value="{{ $s3Config->S3_BUCKET  }}" />
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_REGION">S3_REGION</label>
                        <select name="S3_REGION" class="form-control">
                            <option value="APEast1" @if ($s3Config->S3_REGION=='APEast1' ) selected @endif>APEast1</option>
                            <option value="AFSouth1" @if ($s3Config->S3_REGION=='AFSouth1' ) selected @endif>AFSouth1</option>
                            <option value="APEast1" @if ($s3Config->S3_REGION=='APEast1' ) selected @endif>APEast1</option>
                            <option value="APNortheast1" @if ($s3Config->S3_REGION=='APNortheast1' ) selected @endif>APNortheast1</option>
                            <option value="APNortheast2" @if ($s3Config->S3_REGION=='APNortheast2' ) selected @endif>APNortheast2</option>
                            <option value="APNortheast3" @if ($s3Config->S3_REGION=='APNortheast3' ) selected @endif>APNortheast3</option>
                            <option value="APSouth1" @if ($s3Config->S3_REGION=='APSouth1' ) selected @endif>APSouth1</option>
                            <option value="APSoutheast1" @if ($s3Config->S3_REGION=='APSoutheast1' ) selected @endif>APSoutheast1</option>
                            <option value="APSoutheast2" @if ($s3Config->S3_REGION=='APSoutheast2' ) selected @endif>APSoutheast2</option>
                            <option value="CACentral1" @if ($s3Config->S3_REGION=='CACentral1' ) selected @endif>CACentral1</option>
                            <option value="CNNorth1" @if ($s3Config->S3_REGION=='CNNorth1' ) selected @endif>CNNorth1</option>
                            <option value="CNNorthWest1" @if ($s3Config->S3_REGION=='CNNorthWest1' ) selected @endif>CNNorthWest1</option>
                            <option value="EUCentral1" @if ($s3Config->S3_REGION=='EUCentral1' ) selected @endif>EUCentral1</option>
                            <option value="EUNorth1" @if ($s3Config->S3_REGION=='EUNorth1' ) selected @endif>EUNorth1</option>
                            <option value="EUSouth1" @if ($s3Config->S3_REGION=='EUSouth1' ) selected @endif>EUSouth1</option>
                            <option value="EUWest1" @if ($s3Config->S3_REGION=='EUWest1' ) selected @endif>EUWest1</option>
                            <option value="EUWest2" @if ($s3Config->S3_REGION=='EUWest2' ) selected @endif>EUWest2</option>
                            <option value="EUWest3" @if ($s3Config->S3_REGION=='EUWest3' ) selected @endif>EUWest3</option>
                            <option value="MESouth1" @if ($s3Config->S3_REGION=='MESouth1' ) selected @endif>MESouth1</option>
                            <option value="SAEast1" @if ($s3Config->S3_REGION=='SAEast1' ) selected @endif>SAEast1</option>
                            <option value="USEast1" @if ($s3Config->S3_REGION=='USEast1' ) selected @endif>USEast1</option>
                            <option value="USEast2" @if ($s3Config->S3_REGION=='USEast2' ) selected @endif>USEast2</option>
                            <option value="USGovCloudEast1" @if ($s3Config->S3_REGION=='USGovCloudEast1' ) selected @endif>USGovCloudEast1</option>
                            <option value="USGovCloudWest1" @if ($s3Config->S3_REGION=='USGovCloudWest1' ) selected @endif>USGovCloudWest1</option>
                            <option value="USIsobEast1" @if ($s3Config->S3_REGION=='USIsobEast1' ) selected @endif>USIsobEast1</option>
                            <option value="USIsoEast1" @if ($s3Config->S3_REGION=='USIsoEast1' ) selected @endif>USIsoEast1</option>
                            <option value="USWest1" @if ($s3Config->S3_REGION=='USWest1' ) selected @endif>USWest1</option>
                            <option value="USWest2" @if ($s3Config->S3_REGION=='USWest2' ) selected @endif>USWest2</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- cache extension -->
             <div class="row mb-3">
                <div class="d-flex">
                    <input type="checkbox" class="form-check-input me-2" name="cache_extension" id="cache_extension" {{ $cache_extension_setting == 'on' ? 'checked' : '' }} />
                    <label class="form-check-label" for="cache_extension">Enable cache extension (<a href="#" onclick="event.preventDefault(); document.getElementById('detail_cache_extension').style.display = 'block'">Details</a>)</label>
            </div>
            </div>
                <div id="detail_cache_extension" style="display: none;">
                Cache extension applicable to profiles created from <b>September 26, 2024</b><br/>
                Extensions will be uploaded and stored on a private server <i>(files with the prefix cache_)</i> instead of being stored in the profile<br/>
                This helps <b>save storage space, reduce load times, and improve profile opening speed</b><br/>
                <span style="color:red"><b>The private server administrator is responsible for enabling or disabling this feature</b></span>
            </div>
            <br>
            <button class="btn btn-primary" type="submit">Apply</button>
        </form>

        <br /><br />
        <h3 style="color: #0080C0">Google Drive Configuration</h3><br />
        
        @if($googleDriveConfigured && $googleDriveAccount)
        <div class="alert alert-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-check-circle"></i> <strong>Google Drive đã được cấu hình thành công!</strong><br>
                    <small>Tài khoản: <strong>{{ $googleDriveAccount['name'] }}</strong> ({{ $googleDriveAccount['email'] }})</small>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Bạn có chắc muốn xóa cấu hình Google Drive?')) { window.location.href='/admin/google-drive/reset'; }">
                    Xóa cấu hình
                </button>
            </div>
        </div>
        @else
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Google Drive chưa được cấu hình. Vui lòng thiết lập để sử dụng tính năng backup.
        </div>
        @endif

        @if(!$googleDriveConfigured || !$googleDriveAccount)
        <div class="card mb-4">
            <div class="card-header">
                <strong>Bước 1: Upload Credentials File</strong>
            </div>
            <div class="card-body">
                <p>Tải credentials JSON từ Google Cloud Console và upload tại đây:</p>
                <form action="/admin/google-drive/upload-credentials" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <input type="file" name="credentials" class="form-control" accept=".json" required>
                        <small class="text-muted">File: google-drive-credentials.json</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Credentials</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <strong>Bước 2: Authenticate với Google Drive</strong>
            </div>
            <div class="card-body">
                <button class="btn btn-success mb-3" onclick="getAuthUrl()">Lấy Authorization URL</button>
                <div id="authUrlContainer" style="display: none;">
                    <div class="alert alert-info">
                        <strong>Authorization URL:</strong><br>
                        <a id="authUrlLink" href="#" target="_blank" class="text-break"></a>
                    </div>
                    <p>Sau khi authorize, copy authorization code và paste vào đây:</p>
                    <div class="input-group mb-3">
                        <input type="text" id="authCode" class="form-control" placeholder="Paste authorization code here">
                        <button class="btn btn-primary" onclick="saveToken()">Xác nhận</button>
                    </div>
                    <div id="authResult"></div>
                </div>
            </div>
        </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ $googleDriveConfigured ? 'Cấu hình' : 'Bước 3' }}: Root Folder ID (Optional)</strong>
            </div>
            <div class="card-body">
                <p>Nếu muốn backup vào một folder cụ thể trên Google Drive, nhập Folder ID:</p>
                <form action="/admin/google-drive/save-root-folder" method="POST">
                    @csrf
                    <div class="input-group mb-3">
                        <input type="text" name="folder_id" class="form-control" placeholder="Google Drive Folder ID" value="{{ $googleDriveRootFolderId }}">
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </div>
                    <small class="text-muted">Để trống nếu muốn backup vào root folder</small>
                </form>
            </div>
        </div>

        <br /><br />
        <h3 style="color: #0080C0">Google Drive Backup Manager</h3><br />
        <div class="alert alert-info">
            <strong>Hướng dẫn:</strong> Bật Auto Backup để tự động backup files lên Google Drive khi có thay đổi. 
            Hoặc sử dụng Manual Backup để backup thủ công toàn bộ files của group.
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Auto Backup</th>
                    <th>Drive Folder ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group)
                <tr id="group-{{ $group->id }}">
                    <td>{{ $group->name }}</td>
                    <td>
                        <span class="badge bg-{{ $group->auto_backup ? 'success' : 'secondary' }}" id="status-{{ $group->id }}">
                            {{ $group->auto_backup ? 'ON' : 'OFF' }}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted" id="folder-{{ $group->id }}">
                            {{ $group->google_drive_folder_id ?? 'Chưa tạo' }}
                        </small>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-group-id="{{ $group->id }}" data-auto-backup="{{ $group->auto_backup ? 1 : 0 }}" onclick="toggleAutoBackup(this)">
                            {{ $group->auto_backup ? 'Tắt' : 'Bật' }} Auto Backup
                        </button>
                        <button class="btn btn-sm btn-success" data-group-id="{{ $group->id }}" onclick="manualBackup(this)">
                            <i class="fas fa-upload"></i> Backup to Drive
                        </button>
                        <button class="btn btn-sm btn-info" data-group-id="{{ $group->id }}" onclick="syncFromDrive(this)" {{ $group->google_drive_folder_id ? '' : 'disabled' }}>
                            <i class="fas fa-download"></i> Sync from Drive
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <br /><br />
        <h3 style="color: #0080C0">User manager</h3><br />
        <table class="table">
            <thead>
                <tr>
                    <th>User name</th>
                    <th>Display name</th>
                    <th>Active status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                <tr>
                    <td>{{ $user->user_name }}</td>
                    <td>{{ $user->display_name }}</td>
                    <td>{{ ($user->active == 0 ? 'Deactivated':'Actived') }}</td>
                    <td>
                        @php
                        $activeUrl = url('admin/active-user').'/'.$user->id;
                        @endphp
                        <a href="{{ $activeUrl }}">{{ ($user->active == 0 ? 'Active':'Deactive') }}</a>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>
</body>
<script>
    function handleStorageTypeChange(select) {
        var s3Config = document.getElementById("s3Config");
        if (select.value === "s3") {
            s3Config.style.display = "block";
        } else {
            s3Config.style.display = "none";
        }
    }

    async function toggleAutoBackup(button) {
        const groupId = button.getAttribute('data-group-id');
        const currentStatus = parseInt(button.getAttribute('data-auto-backup'));
        const newStatus = currentStatus ? 0 : 1;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        try {
            const response = await fetch(`/admin/groups/toggle-auto-backup/${groupId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    auto_backup: newStatus
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update UI
                const statusBadge = document.getElementById(`status-${groupId}`);
                statusBadge.className = newStatus ? 'badge bg-success' : 'badge bg-secondary';
                statusBadge.textContent = newStatus ? 'ON' : 'OFF';

                button.className = 'btn btn-sm btn-primary';
                button.textContent = newStatus ? 'Tắt Auto Backup' : 'Bật Auto Backup';
                button.setAttribute('data-auto-backup', newStatus);
                button.disabled = false;

                alert(data.message || 'Cập nhật thành công!');
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
                button.disabled = false;
                button.textContent = currentStatus ? 'Tắt Auto Backup' : 'Bật Auto Backup';
            }
        } catch (error) {
            alert('Lỗi kết nối: ' + error.message);
            button.disabled = false;
            button.textContent = currentStatus ? 'Tắt Auto Backup' : 'Bật Auto Backup';
        }
    }

    async function manualBackup(button) {
        const groupId = button.getAttribute('data-group-id');
        
        if (!confirm('Bạn muốn backup tất cả files của group này lên Google Drive?')) {
            return;
        }
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting...';

        try {
            const response = await fetch(`/admin/groups/manual-backup/${groupId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                const result = data.data;
                alert(`${data.message}\n\nTổng files: ${result.total}\nTrạng thái: ${result.status}\n\nQuá trình backup sẽ chạy background.\nKiểm tra log để theo dõi tiến trình.`);
                
                // Reload page to update folder ID if created
                setTimeout(() => location.reload(), 2000);
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
                button.disabled = false;
                button.textContent = 'Manual Backup';
            }
        } catch (error) {
            alert('Lỗi kết nối: ' + error.message);
            button.disabled = false;
            button.textContent = 'Manual Backup';
        }
    }

    async function syncFromDrive(button) {
        const groupId = button.getAttribute('data-group-id');
        
        if (!confirm('Bạn muốn sync files từ Google Drive về local?\n\nCảnh báo: Files local sẽ bị ghi đè nếu khác với version trên Drive!')) {
            return;
        }
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';

        try {
            const response = await fetch(`/admin/groups/sync-from-drive/${groupId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                const result = data.data;
                alert(`${data.message}\n\nTổng files: ${result.total}\nTrạng thái: ${result.status}\n\nQuá trình sync sẽ chạy background.\nKiểm tra log để theo dõi tiến trình.`);
                
                setTimeout(() => location.reload(), 2000);
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
                button.disabled = false;
                button.textContent = 'Sync from Drive';
            }
        } catch (error) {
            alert('Lỗi kết nối: ' + error.message);
            button.disabled = false;
            button.textContent = 'Sync from Drive';
        }
    }

    // Get token from cookie if exists
    if (!localStorage.getItem('token')) {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'token') {
                localStorage.setItem('token', value);
                break;
            }
        }
    }

    // Google Drive Auth Functions
    async function getAuthUrl() {
        try {
            const response = await fetch('/admin/google-drive/auth-url');
            const data = await response.json();

            if (data.success) {
                document.getElementById('authUrlContainer').style.display = 'block';
                const link = document.getElementById('authUrlLink');
                link.href = data.auth_url;
                link.textContent = data.auth_url;
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
            }
        } catch (error) {
            alert('Lỗi: ' + error.message);
        }
    }

    async function saveToken() {
        const authCode = document.getElementById('authCode').value.trim();
        
        if (!authCode) {
            alert('Vui lòng nhập authorization code!');
            return;
        }

        try {
            const response = await fetch('/admin/google-drive/save-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    auth_code: authCode
                })
            });

            const data = await response.json();

            if (data.success) {
                const resultDiv = document.getElementById('authResult');
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Kết nối thành công!</strong><br>
                        Tài khoản: ${data.user.name} (${data.user.email})
                    </div>
                `;
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
            }
        } catch (error) {
            alert('Lỗi: ' + error.message);
        }
    }
</script>

</html>