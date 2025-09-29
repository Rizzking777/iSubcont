<?php
// === Notifikasi CRUD (Insert/Update/Delete) ===
if (isset($_SESSION['green_notif']) || isset($_SESSION['red_notif'])):
    $notif_status  = isset($_SESSION['green_notif']) ? 'success' : 'danger';
    $notif_message = $_SESSION['green_notif'] ?? $_SESSION['red_notif'];
    $notif_icon    = $notif_status === 'success' ? 'bi-emoji-smile me-2 fs-3' : 'bi-emoji-neutral me-2 fs-3';
    $notif_title   = $notif_status === 'success' ? 'Berhasil!' : 'Gagal!';
?>
  <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
    <div id="liveToast" class="toast align-items-center text-white bg-<?= $notif_status ?> border-0 show"
         role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          <div class="d-flex align-items-center mb-1">
            <i class="bi <?= $notif_icon ?>"></i>
            <div class="me-auto fw-medium"><?= $notif_title ?></div>
          </div>
          <?= $notif_message ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-progress bg-light">
        <div class="toast-progress-bar bg-<?= $notif_status ?>"></div>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['green_notif'], $_SESSION['red_notif']); ?>
<?php endif; ?>


<?php
// === Notifikasi Login ===
if (isset($_SESSION['login_status'])):
    $login_status  = $_SESSION['login_status'];
    $login_success = $login_status === 'success';
    $login_icon    = $login_success ? 'bi-emoji-smile me-2 fs-3' : 'bi-emoji-neutral me-2 fs-3';
    $login_title   = $login_success ? 'Login Berhasil' : 'Login Gagal';
    $login_message = $login_success
        ? 'Anda berhasil login.'
        : 'NIK atau Password salah. Hubungi Admin jika memerlukan bantuan.';
?>
  <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
    <div id="liveToast" class="toast align-items-center text-white bg-<?= $login_status ?> border-0 show"
         role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          <div class="d-flex align-items-center mb-1">
            <i class="bi <?= $login_icon ?>"></i>
            <strong><?= $login_title ?></strong>
          </div>
          <small><?= $login_message ?></small>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-progress bg-light">
        <div class="toast-progress-bar bg-<?= $login_status ?>"></div>
      </div>
    </div>
  </div>
  <?php unset($_SESSION['login_status']); ?>
<?php endif; ?>
