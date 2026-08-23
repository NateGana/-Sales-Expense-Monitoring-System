<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access denied</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
  <div class="text-center">
    <h1 class="display-6 fw-semibold text-danger">Access denied</h1>
    <p class="text-muted">Your account role does not have permission to view this page.</p>
    <a href="<?= base_url('dashboard.php') ?>" class="btn btn-dark mt-2">Back to dashboard</a>
  </div>
</body>
</html>
