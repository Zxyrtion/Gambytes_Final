<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Error - Gambytes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container error-container">
        <div class="card error-card">
            <div class="card-body p-5">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="card-title mb-3">Booking Error</h2>
                <p class="card-text text-muted mb-4">
                    We encountered an error while processing your booking request.
                </p>
                
                <div class="alert alert-danger text-start">
                    <i class="fas fa-info-circle"></i>
                    <strong>Error Details:</strong><br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="/scheduling" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Try Again
                    </a>
                    <a href="/dashboard" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <p class="text-muted">
                <small>If this problem persists, please contact our support team.</small>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
