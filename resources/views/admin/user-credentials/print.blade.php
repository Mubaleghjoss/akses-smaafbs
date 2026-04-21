<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kredensial Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #111827;
        }

        .meta {
            margin-bottom: 16px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #111827;
            padding: 8px;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #fff176;
            text-align: left;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 20px;
        }

        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <h1>Daftar Kredensial Reset Password</h1>

    <div class="meta">
        <div><strong>Dibuat pada:</strong> {{ $generatedAt }}</div>
        <div><strong>Dibuat oleh:</strong> {{ $generatedBy }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">No</th>
                <th>Nama</th>
                <th>Username</th>
                <th>Password Baru</th>
                <th style="width: 120px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($credentials as $index => $credential)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $credential['name'] ?? '-' }}</td>
                    <td>{{ $credential['username'] ?? '-' }}</td>
                    <td>{{ $credential['password'] ?? '-' }}</td>
                    <td>Reset default</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Tidak ada data kredensial.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
