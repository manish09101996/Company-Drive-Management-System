<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - {{ config('app.name', 'Company Drive') }}</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-header {
            background: #0f172a;
            color: #ffffff;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            border-bottom: 1px solid #334155;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand-header">
            <div class="brand-icon">
                <i class="bi bi-hdd-network-fill"></i>
            </div>
            <h4 class="fw-bold mb-1">Arkle Homes Drive</h4>
            <p class="text-secondary small mb-0">Document Management & Google Takeout Import Center</p>
        </div>

        <div class="p-4 p-sm-5">
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <div class="fw-bold mb-1">Authentication Error</div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold text-secondary">Work Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="{{ old('email', 'admin@arklehomes.com.au') }}" required autofocus placeholder="name@arklehomes.com.au">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" value="Admin@12345" required placeholder="••••••••">
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" checked>
                    <label class="form-check-label small text-secondary" for="remember">Keep me signed in on this device</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Drive
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <div class="text-muted small mb-2 fw-semibold">Arkle Homes Team Accounts:</div>
                <div class="badge bg-light text-dark border p-2 text-start font-monospace small w-100 mb-1" style="font-size: 0.72rem; line-height: 1.5;">
                    <div><strong>Admin:</strong> admin@arklehomes.com.au</div>
                    <div><strong>SK Mann:</strong> skmann@arklehomes.com.au</div>
                    <div><strong>Garima:</strong> garima@arklehomes.com.au</div>
                    <div><strong>Ishan:</strong> ishan@arklehomes.com.au</div>
                    <div><strong>Sean:</strong> sean@arklehomes.com.au</div>
                    <div class="border-top mt-1 pt-1 text-primary"><strong>Default Password:</strong> Admin@12345</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
