<?php require __DIR__ . '/../partials/_footer_overlays.blade.php'; ?>

<script src="/assets/js/modules/utilities.js"></script>
<script>
<?php if (in_array($page, ['login', 'register', 'forgot-password', 'verify-otp', 'reset-password'])): ?>
<?php require __DIR__ . '/../partials/_scripts_auth.blade.php'; ?>
<?php elseif ($page === 'landing'): ?>
<?php require __DIR__ . '/../partials/_scripts_landing.blade.php'; ?>
<?php else: ?>
<?php require __DIR__ . '/../partials/_scripts_app.blade.php'; ?>
<?php endif; ?>
</script>

<script src="/assets/js/modules/common-features.js"></script>


<!-- Modern Layout Enhancements -->
</body>
</html>

