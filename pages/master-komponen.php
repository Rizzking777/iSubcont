<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('master_komponen'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

// Ambil data komponen proses
$sql = "
    SELECT 
    k_out.id_komponen AS id_output,
    MAX(k.model) AS model,
    MAX(k.style) AS style,
    GROUP_CONCAT(DISTINCT k_in.nama_komponen ORDER BY k_in.nama_komponen SEPARATOR ', ') AS input_komponen,
    GROUP_CONCAT(DISTINCT vp.id_vendor ORDER BY v.name_vendor SEPARATOR ',') AS vendor_ids,
    k_out.nama_komponen AS output_komponen,
    GROUP_CONCAT(DISTINCT v.name_vendor ORDER BY v.name_vendor SEPARATOR ', ') AS vendors
FROM tbl_komponen_proses p
JOIN tbl_komponen k_in 
      ON p.id_input = k_in.id_komponen
JOIN tbl_komponen k_out 
      ON p.id_output = k_out.id_komponen
JOIN tbl_komponen k 
      ON k.id_komponen = p.id_output
LEFT JOIN tbl_vendor_proses vp 
      ON vp.id_proses = p.id_proses         -- ✅ join sesuai struktur
LEFT JOIN tbl_vendor v 
      ON v.id_vendor = vp.id_vendor
WHERE k_in.is_deleted = 0 
  AND k_out.is_deleted = 0
GROUP BY k_out.id_komponen
ORDER BY k.timestamp desc;
";

$result = $conn->query($sql);

?>

<style>
  .toast-progress {
    height: 4px;
    width: 100%;
    overflow: hidden;
    border-radius: 0 0 0.375rem 0.375rem;
  }

  .toast-progress-bar {
    height: 100%;
    animation: progressBar 3s linear forwards;
  }

  @keyframes progressBar {
    0% {
      width: 100%;
    }

    100% {
      width: 0%;
    }
  }

  /* Samain tinggi select2 sama input bootstrap */
  .select2-container .select2-selection--single {
    height: calc(2.25rem + 2px) !important;
    /* tinggi form-control Bootstrap */
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
  }

  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 1.5 !important;
    /* biar text center */
  }

  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100% !important;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Master Komponen</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../assets/img/Logo-Stg.png" rel="icon">
  <link href="../assets/img/Logo-Stg.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets/css/style.css" rel="stylesheet">

  <!-- Tambahkan pustaka Select2 di bagian <head> -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Pastikan jQuery ada -->

  <!-- Select2 CSS & JS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- Datatables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'master_komponen';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Master Data Komponen
      </h1>
    </div>

    <!-- Modal Tambah Komponen -->
    <div class="modal fade" id="modalAddKomponen" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <form action="./../config/function.php" method="POST" id="formAddKomponen">
          <div class="modal-content">
            <!-- Header -->
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title d-flex align-items-center">
                <i class="bi bi-puzzle me-2"></i> Tambah Komponen Proses
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">
              <div class="row g-3">

                <!-- Model -->
                <div class="col-md-6">
                  <label class="form-label">Model</label>
                  <input type="text" name="model" id="model" class="form-control" placeholder="Masukkan nama model" required>
                </div>

                <!-- Style -->
                <div class="col-md-6">
                  <label class="form-label">Style (opsional)</label>
                  <input type="text" name="style" id="style" class="form-control" placeholder="Masukkan style (jika ada)">
                </div>

                <!-- Input Komponen (multiple) -->
                <div class="col-md-12">
                  <label class="form-label">Input Komponen</label>
                  <div id="inputKomponenWrapper">
                    <div class="d-flex mb-2">
                      <input type="text" name="input_komponen[]" class="form-control me-2" placeholder="Nama komponen input" required>
                      <button type="button" class="btn btn-success btnAddInput">
                        <i class="bi bi-plus-circle"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Output Komponen -->
                <div class="col-md-12">
                  <label class="form-label">Output Komponen</label>
                  <input type="text" name="output_komponen" class="form-control" placeholder="Nama komponen output" required>
                </div>

                <!-- Vendor -->
                <div class="col-md-12">
                  <label class="form-label">Vendor</label>
                  <select name="vendor_id" id="vendorSelect" class="form-select2" required style="width:100%;">
                    <option value="">Pilih Vendor</option>
                    <?php
                    $res_vendor = $conn->query("SELECT id_vendor, name_vendor FROM tbl_vendor WHERE is_deleted = 0 ORDER BY name_vendor");
                    while ($v = $res_vendor->fetch_assoc()):
                    ?>
                      <option value="<?= $v['id_vendor'] ?>">
                        <?= htmlspecialchars($v['name_vendor']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="submit-komponen">
                <i class="bi bi-check-circle me-1"></i> Simpan
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header text-black">
              <div class="d-flex justify-content-between align-items-center w-100">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddKomponen">
                  <i class="bi bi-plus-circle me-1"></i> Add
                </button>
              </div>
            </div>

            <div class="card-body" style="margin-top: 10px;">

              <!-- Table with stripped rows -->
              <div class="table-responsive" id="userTable">
                <table id="example1" class="table table-bordered table-striped text-center align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">#</th>
                      <th class="text-center">Model</th>
                      <th class="text-center">Style</th>
                      <th class="text-center">Komponen Input</th>
                      <th class="text-center">Komponen Output</th>
                      <th class="text-center">Vendor</th>
                      <th class="text-center">Options</th>
                    </tr>
                  </thead>
                  <tbody class="table-border-bottom-0">

                    <?php $i = 1; ?>
                    <?php foreach ($result as $row) : ?>
                      <tr>
                        <td><?= $i ?></td>
                        <td><?= htmlspecialchars($row['model']) ?></td>
                        <td><?= htmlspecialchars($row['style']) ?></td>
                        <td><?= htmlspecialchars($row['input_komponen']) ?></td>
                        <td><?= htmlspecialchars($row['output_komponen']) ?></td>
                        <td><?= htmlspecialchars($row['vendors']) ?></td>
                        <td>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button"
                              id="dropdownMenu<?= $row['id_output']; ?>"
                              data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                              <li>
                                <a class="dropdown-item editKomponenBtn"
                                  href="#"
                                  data-bs-toggle="modal"
                                  data-bs-target="#editKomponenModal"
                                  data-id="<?= $row['id_output']; ?>"
                                  data-model="<?= htmlspecialchars($row['model']); ?>"
                                  data-style="<?= htmlspecialchars($row['style']); ?>"
                                  data-input="<?= htmlspecialchars($row['input_komponen']); ?>"
                                  data-output="<?= htmlspecialchars($row['output_komponen']); ?>"
                                  data-vendor-id="<?= $row['vendor_ids']; ?>">
                                  <i class="bi bi-pencil me-2"></i> Edit
                                </a>
                              </li>
                              <li>
                                <form action="./../config/function.php" method="post"
                                  onsubmit="return confirm('Yakin ingin hapus komponen ini?');">
                                  <input type="hidden" name="id_output" value="<?= htmlspecialchars($row['id_output']); ?>">
                                  <button type="submit" name="remove-komponen" class="dropdown-item text-danger">
                                    <i class="bi bi-trash me-2"></i> Remove
                                  </button>
                                </form>
                              </li>
                            </ul>

                          </div>
                        </td>
                      </tr>

                      <?php $i++; ?>
                    <?php endforeach; ?>

                  </tbody>
                </table>
              </div>
              <!-- End Table with stripped rows -->
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Modal Edit Komponen -->
    <div class="modal fade" id="editKomponenModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <form action="./../config/function.php" method="POST" id="formEditKomponen">
          <div class="modal-content">
            <!-- Header -->
            <div class="modal-header text-black" style="background-color: DDDAD0;">
              <h5 class="modal-title d-flex align-items-center" id="editUserModalLabel">
                <i class="bi bi-puzzle me-2"></i> Edit Komponen Proses
              </h5>
              <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Body -->
            <div class="modal-body">
              <input type="hidden" name="id_output" id="edit_id_output">

              <div class="row g-3">
                <!-- Model -->
                <div class="col-md-6">
                  <label class="form-label">Model</label>
                  <input type="text" class="form-control" name="model" id="edit_model" readonly>
                </div>

                <!-- Style -->
                <div class="col-md-6">
                  <label class="form-label">Style</label>
                  <input type="text" class="form-control" name="style" id="edit_style" readonly>
                </div>

                <!-- Input Komponen -->
                <div class="col-md-12">
                  <label class="form-label">Input Komponen</label>
                  <div id="editInputWrapper"></div>
                </div>

                <!-- Output Komponen -->
                <div class="col-md-12">
                  <label class="form-label">Output Komponen</label>
                  <input type="text" class="form-control" name="output_komponen" id="edit_output" required>
                </div>

                <!-- Vendor -->
                <div class="col-md-12">
                  <label class="form-label">Vendor</label>
                  <select name="vendor_id" id="edit_vendorSelect" class="form-select2" required style="width:100%;">
                    <option value="">Pilih Vendor</option>
                    <?php
                    $res_vendor = $conn->query("SELECT id_vendor, name_vendor FROM tbl_vendor WHERE is_deleted = 0 ORDER BY name_vendor");
                    while ($v = $res_vendor->fetch_assoc()):
                    ?>
                      <option value="<?= $v['id_vendor']; ?>"><?= htmlspecialchars($v['name_vendor']); ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="update-komponen">
                <i class="bi bi-check-circle me-1"></i> Update
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <?php include_once __DIR__ . '/../includes/footer.php' ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="../assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/chart.js/chart.umd.js"></script>
  <script src="../assets/vendor/echarts/echarts.min.js"></script>
  <script src="../assets/vendor/quill/quill.min.js"></script>
  <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="../assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>

  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- DataTables core -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

  <!-- Responsive extension -->
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

  <!-- Buttons extension -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

  <script>
    $(function() {
      $("#example1").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        // "buttons": ["copy", "excel"]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
      $('#example2').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
      });
    });
  </script>

  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toastEl = document.getElementById('liveToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
          delay: 3000
        });
        toast.show();
      }
    });
  </script>

  <script>
    // Event klik tombol edit
$(document).on('click', '.editKomponenBtn', function() {
    // Ambil data dari tombol
    let id_output = $(this).data('id');
    let model = $(this).data('model');
    let style = $(this).data('style');
    let output = $(this).data('output');
    let inputs = $(this).data('input').split(',');
    let vendorId = $(this).data('vendor-id'); // pakai id vendor

    // Set field modal
    $('#edit_id_output').val(id_output);
    $('#edit_model').val(model);
    $('#edit_style').val(style);
    $('#edit_output').val(output);

    // Generate input komponen
    $('#editInputWrapper').empty();
    inputs.forEach(function(item) {
        $('#editInputWrapper').append(`
            <div class="mb-2">
                <input type="text" name="input_komponen[]" class="form-control" value="${item.trim()}">
            </div>
        `);
    });

    // Set vendor
    $('#edit_vendorSelect').val(vendorId).trigger('change'); // untuk Select2
});

// Inisialisasi Select2 saat modal tampil
$('#editKomponenModal').on('shown.bs.modal', function () {
    $('#edit_vendorSelect').select2({
        dropdownParent: $('#editKomponenModal'), // penting supaya dropdown muncul di modal
        width: '100%'
    });
});

  </script>

  <script>
    $(document).ready(function() {
      // --- Inisialisasi Select2 untuk Vendor ---
      $('#vendorSelect').select2({
        placeholder: "Pilih vendor",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#modalAddKomponen') // ⬅️ ini penting biar nggak kabur keluar modal
      });

      // --- Tambah input komponen ---
      $(document).on('click', '.btnAddInput', function() {
        let html = `
        <div class="d-flex mb-2">
          <input type="text" name="input_komponen[]" class="form-control me-2" placeholder="Nama komponen input" required>
          <button type="button" class="btn btn-danger btnRemoveInput">
            <i class="bi bi-dash-circle"></i>
          </button>
        </div>`;
        $('#inputKomponenWrapper').append(html);
      });

      // --- Hapus input komponen ---
      $(document).on('click', '.btnRemoveInput', function() {
        $(this).closest('div').remove();
      });
    });
  </script>

</body>

</html>