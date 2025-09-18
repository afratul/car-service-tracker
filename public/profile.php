<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';

require_login();

$user = current_user(); // id, name, email, role, etc.
$currentPhoto = $user['profile_photo'] ?? null; // DB stores just the filename (or null)

// Ensure phone fields are available even if current_user() doesn't include them
$uStmt = db()->prepare("SELECT phone_number, sms_opt_in FROM users WHERE id=?");
$uStmt->execute([$user['id']]);
$uExtra = $uStmt->fetch();
if ($uExtra) {
    $user['phone_number'] = $uExtra['phone_number'] ?? '';
    $user['sms_opt_in']   = (int)($uExtra['sms_opt_in'] ?? 0);
}

$success = '';
$errors  = [];

if (isset($_GET['saved'])) {
    $success = 'Profile updated successfully.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name'] ?? '');
    $curr    = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password'] ?? '';
    $new2    = $_POST['confirm_password'] ?? '';
    // 0) Phone & SMS (optional fields)
$phone    = trim($_POST['phone_number'] ?? '');
$smsOptIn = isset($_POST['sms_opt_in']) ? 1 : 0;

// Basic phone validation (E.164-ish). Empty is allowed.
if ($phone !== '' && !preg_match('/^\+?[0-9]{8,15}$/', $phone)) {
    $errors[] = 'Enter a valid phone number in international format (e.g., +8801XXXXXXXXX).';
}


    // 1) Update name (optional)
    if ($newName === '') {
        $errors[] = 'Name cannot be empty.';
    }

    // 2) Update password (optional)
    $changePw = ($curr !== '' || $new1 !== '' || $new2 !== '');
    if ($changePw) {
        if ($curr === '' || $new1 === '' || $new2 === '') {
            $errors[] = 'To change password, fill all password fields.';
        } elseif ($new1 !== $new2) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($new1) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            // verify current password
            $stmt = db()->prepare("SELECT password_hash FROM users WHERE id=?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($curr, $row['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }

    if (!$errors) {
          // --- Optional: Profile photo upload ---
    $newPhotoFilename = null;

    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed. Please try again.';
        } else {
            // Size check
            if ($_FILES['photo']['size'] > PROFILE_MAX_BYTES) {
                $errors[] = 'Photo is too large (max 2 MB).';
            } else {
                // MIME check using finfo
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['photo']['tmp_name']);
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($allowed[$mime])) {
                    $errors[] = 'Only JPG/PNG/WEBP images are allowed.';
                } else {
                    // Generate safe unique name
                    $ext = $allowed[$mime];
                    $newPhotoFilename = 'u'.$user['id'].'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;

                    // Move to uploads/profile
                    $destPath = rtrim(PROFILE_UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newPhotoFilename;
                    if (!@move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                        $errors[] = 'Could not save the uploaded photo.';
                        $newPhotoFilename = null;
                    }
                }
            }
        }
    }

    // If any errors occurred during upload, do not update DB yet
    if (!$errors) {
        // update name
        // update name + phone + sms opt-in
        $stmt = db()->prepare("UPDATE users SET name=?, phone_number=?, sms_opt_in=? WHERE id=?");
        $stmt->execute([$newName, $phone, $smsOptIn, $user['id']]);
        $user['name'] = $newName;
        $user['phone_number'] = $phone;
        $user['sms_opt_in'] = $smsOptIn;

        // update password if requested
        if ($changePw && $curr !== '' && $new1 !== '' && $new1 === $new2) {
            $stmt = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([password_hash($new1, PASSWORD_DEFAULT), $user['id']]);
        }

        // update profile photo if a new one was saved
        if ($newPhotoFilename) {
            // delete old photo (only if it lives under our profile dir and is a simple filename)
            if ($currentPhoto && preg_match('/^[a-z0-9._-]+$/i', $currentPhoto)) {
                $oldPath = rtrim(PROFILE_UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $currentPhoto;
                if (is_file($oldPath)) { @unlink($oldPath); }
            }

            $stmt = db()->prepare("UPDATE users SET profile_photo=? WHERE id=?");
            $stmt->execute([$newPhotoFilename, $user['id']]);
            $currentPhoto = $newPhotoFilename; // refresh for the view
        }

        // refresh session so navbar/profile see updates immediately
$_SESSION['user']['name'] = $newName;
if ($newPhotoFilename) {
    $_SESSION['user']['profile_photo'] = $newPhotoFilename;
}

$_SESSION['user']['phone_number'] = $phone;
$_SESSION['user']['sms_opt_in']   = $smsOptIn;



        $success = 'Profile updated successfully.';
        header('Location: profile.php?saved=1');
exit;


    }
    }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>My Profile</h2>

<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>


<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" class="row g-3" enctype="multipart/form-data">
     <?php
  // Build photo URL (if set), else a default avatar
  if ($currentPhoto && preg_match('/^[a-z0-9._-]+$/i', $currentPhoto)) {
      $photoUrl = rtrim(BASE_URL, '/') . '/public/uploads/profile/' . $currentPhoto;
  } else {
      $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
        <rect width="100%" height="100%" fill="#e9ecef"/>
        <text x="50%" y="52%" text-anchor="middle" font-size="32" fill="#6c757d" font-family="Arial,Helvetica,sans-serif">👤</text>
      </svg>';
      $photoUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
  }
?>
<div class="col-md-3">
  <label class="form-label d-block">Profile photo</label>
  <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Profile" class="rounded border" style="width:120px;height:120px;object-fit:cover;">
  <input type="file" class="form-control mt-2" name="photo" accept="image/*">
  <div class="form-text">JPG/PNG/WEBP · Max 2 MB</div>
</div>
 
  <div class="col-md-9">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
  </div>

  <div class="mb-3">
    <label class="form-label">Name</label>
    <input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
  </div>
  <div class="mb-3">
  <label class="form-label">Phone number (for SMS alerts)</label>
  <input name="phone_number" class="form-control"
         value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>"
         placeholder="+8801XXXXXXXXX">
  <div class="form-text">Use international format (E.164), e.g., +8801…</div>
</div>

<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" name="sms_opt_in" id="sms_opt_in" value="1"
         <?= !empty($user['sms_opt_in']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="sms_opt_in">
    Receive SMS alerts for overdue services
  </label>
</div>

</div>



      <div class="col-12"><hr></div>
      <div class="col-12">
        <div class="fw-semibold">Change password (optional)</div>
        <div class="form-text">Leave blank to keep your current password.</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">Current password</label>
        <input type="password" class="form-control" name="current_password">
      </div>
      <div class="col-md-4">
        <label class="form-label">New password</label>
        <input type="password" class="form-control" name="new_password" minlength="6">
      </div>
      <div class="col-md-4">
        <label class="form-label">Confirm new password</label>
        <input type="password" class="form-control" name="confirm_password" minlength="6">
      </div>

      <div class="col-12">
        <button class="btn btn-primary">Save changes</button>
        <a class="btn btn-secondary" href="dashboard.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
