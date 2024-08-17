<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
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
        <h3 style="color: #0080C0">Admin site</h3>
        @if (Session::has('msg'))
            <div class="alert alert-success">
                {{ Session::get('msg')}}
            </div>
        @endif
        <a class="badge bg-danger" href="{{ url('admin/auth/logout') }}">Logout</a>
        &nbsp;<a href="{{ url('admin/reset-profile-status') }}" class="badge bg-success">Reset profile status</a>
        <br/><br/><br/>

        <h3 style="color: #0080C0">Storage setting</h3><br/>
        <form action="admin/set-storage-type">
            <select name="type" class="form-control">
                <option value="s3" @if ($storageType == 's3') selected @endif>S3 (setting api in .env file)</option>
                <option value="hosting" @if ($storageType == 'hosting') selected @endif>Hosting (Recommended for LAN)</option>
            </select>
            <br>
            <button class="btn btn-primary" type="submit">Apply</button>
        </form>

        <br/><br/>
        <h3 style="color: #0080C0">User manager</h3><br/>
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
</html>
