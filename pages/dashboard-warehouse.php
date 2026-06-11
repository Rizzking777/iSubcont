<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('wh_dashboard'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

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

    body {
        background: #f8fafc;
    }

    .dashboard-title {
        background: #f0e6d2;
        padding: 12px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
    }

    .dashboard-title h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
    }

    .dashboard-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        transition: all .2s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, .06);
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }

    .section-header i {
        color: #334155;
    }

    .sub-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        background: #fcfcfd;
        min-height: 320px;
        display: flex;
        align-items: center;
    }

    .sub-title {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 24px;
        color: #0f172a;
    }

    .chart-title {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 10px;
        color: #0f172a;
    }

    .metric-group {
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 100%;
    }

    .metric-group-full {
        height: 280px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .metric-row {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
    }

    .metric-label {
        width: 80px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .metric-value {
        min-width: 75px;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -.5px;
        color: #0f172a;
    }

    .progress-custom {
        flex: 1;
        height: 38px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, .05);
    }

    .progress-custom .progress-bar {
        border-radius: 10px;
        box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .08);
        transition: all .25s ease;
    }

    .progress-custom:hover .progress-bar {
        filter: brightness(1.08);
        transform: scaleY(1.08);
    }

    .bg-in {
        background: #9bc47c !important;
    }

    .bg-out {
        background: #e6a775 !important;
    }

    .bg-inventory {
        background: #357ABD !important;
    }

    .chart-container {
        height: 280px;
    }

    .tooltip-inner {
        background: #0f172a;
        color: #ffffff;
        font-size: 12px;
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 500;
    }

    .tooltip.bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #0f172a;
    }

    .chart-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        width: 100%;
    }

    .dashboard-card {
        position: relative;
        overflow: hidden;
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(to right,
                #5f84ad,
                #7aa37a);
    }

    .progress-bar {
        position: relative;
        overflow: hidden;
    }

    .progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: -40%;
        width: 40%;
        height: 100%;
        background: rgba(255, 255, 255, .18);
        transform: skewX(-20deg);
    }

    /* MODAL  */
    .dashboard-detail-modal {
        border: 0;
        border-radius: 20px;
    }

    .dashboard-modal-header {
        border-bottom: 1px solid #e2e8f0;
        padding: 20px 24px;
    }

    .dashboard-modal-footer {
        border-top: 1px solid #e2e8f0;
        padding: 16px 24px;
    }

    .dashboard-modal-subtitle {
        font-size: 13px;
        color: #64748b;
        margin-top: 2px;
    }

    .dashboard-detail-table thead th {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
    }

    .dashboard-detail-table tbody td {
        font-size: 13px;
        color: #475569;
        vertical-align: middle;
        white-space: nowrap;
    }

    .dashboard-detail-table tbody tr:hover {
        background: #f8fafc;
    }

    .btn-export {
        background: #2f8a9e;
        color: #ffffff;
        border-radius: 10px;
        padding: 8px 14px;
        font-weight: 600;
    }

    .btn-export:hover {
        background: #256d7d;
        color: #ffffff;
    }

    /* DATATABLE */

    .dashboard-detail-table {
        width: 100% !important;
    }

    .dashboard-detail-table th,
    .dashboard-detail-table td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .dataTables_scrollHeadInner,
    .dataTables_scrollHeadInner table {
        width: 100% !important;
    }

    table.dataTable {
        width: 100% !important;
    }

    .vendor-kpi-card {
        cursor: pointer;
        transition: all .2s ease;
    }

    .vendor-kpi-card:hover {
        transform: translateY(-2px);
        border-color: #006aff !important;
        box-shadow: 0 6px 14px rgba(15, 23, 42, .08);
    }

    .vendor-kpi-table-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
    }

    #vendorKpiDetailTable {
        width: max-content;
        min-width: 100%;
    }

    #vendorKpiDetailTable th,
    #vendorKpiDetailTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #vendorKpiDetailTable th {
        font-weight: 700;
    }

    #vendorKpiDetailModal .dataTables_length,
    #vendorKpiDetailModal .dataTables_filter {
        margin-bottom: 12px;
    }

    #vendorKpiDetailModal .dataTables_info,
    #vendorKpiDetailModal .dataTables_paginate {
        margin-top: 12px;
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>iSubcont - Dashboards</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>

<body>

    <!-- Header -->
    <?php
    $page = 'wh_dashboard';
    include_once __DIR__ . '/../includes/header.php';
    ?>
    <!-- End Header -->

    <main id="main" class="main">

        <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
                Warehouse Dashboard Monitoring
            </h1>
        </div>


        <section class="section">



            <div class="row g-3">

                <!-- ====================================================== -->
                <!-- WAREHOUSE OVERVIEW -->
                <!-- ====================================================== -->

                <div class="col-lg-8">

                    <div class="dashboard-card h-100">

                        <div class="section-header">
                            <i class="bi bi-box-seam"></i>
                            <span>Warehouse Overview</span>
                        </div>

                        <div class="row g-3">

                            <!-- ===================================== -->
                            <!-- READY TRANSFER TO VENDOR -->
                            <!-- ===================================== -->

                            <div class="col-lg-6">

                                <div class="sub-card h-100">

                                    <div class="metric-group">

                                        <div class="sub-title">
                                            Ready Transfer to Vendor
                                        </div>

                                        <!-- RECEIVE -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Receive
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rtTooltipReceive"
                                                data-section="ready_transfer"
                                                data-type="receive"
                                                title="">

                                                <div
                                                    id="rtBarReceive"
                                                    class="progress-bar bg-in"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rtReceive"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <!-- TRANSFER -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Transfer
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rtTooltipTransfer"
                                                data-section="ready_transfer"
                                                data-type="transfer"
                                                title="">

                                                <div
                                                    id="rtBarTransfer"
                                                    class="progress-bar bg-out"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rtTransfer"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <!-- INVENTORY -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Inventory
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rtTooltipInventory"
                                                data-section="ready_transfer"
                                                data-type="inventory"
                                                title="">

                                                <div
                                                    id="rtBarInventory"
                                                    class="progress-bar bg-inventory"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rtInventory"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <hr>

                                        <div>

                                            <div class="chart-title">
                                                Detail Inventory Per-Line
                                            </div>

                                            <div class="chart-scroll">

                                                <div id="chartReadyTransferVendor" class="chart-container"></div>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                            </div>

                            <!-- ===================================== -->
                            <!-- RETURN FROM VENDOR -->
                            <!-- ===================================== -->

                            <div class="col-lg-6">

                                <div class="sub-card h-100">

                                    <div class="metric-group">

                                        <div class="sub-title">
                                            Return from Vendor
                                        </div>

                                        <!-- RECEIVE -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Receive
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rvTooltipReceive"
                                                data-section="return_vendor"
                                                data-type="receive"
                                                title="">

                                                <div
                                                    id="rvBarReceive"
                                                    class="progress-bar bg-in"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rvReceive"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <!-- SEND PRODUCTION -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Send Production
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rvTooltipSendProd"
                                                data-section="return_vendor"
                                                data-type="send_prod"
                                                title="">

                                                <div
                                                    id="rvBarSendProd"
                                                    class="progress-bar bg-out"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rvSendProd"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <!-- INVENTORY -->
                                        <div class="metric-row">

                                            <div class="metric-label">
                                                Inventory
                                            </div>

                                            <div
                                                class="progress progress-custom clickable-card"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                id="rvTooltipInventory"
                                                data-section="return_vendor"
                                                data-type="inventory"
                                                title="">

                                                <div
                                                    id="rvBarInventory"
                                                    class="progress-bar bg-inventory"
                                                    style="width:0%">
                                                </div>

                                            </div>

                                            <div
                                                id="rvInventory"
                                                class="metric-value">
                                                0
                                            </div>

                                        </div>

                                        <hr>

                                        <div>

                                            <div class="chart-title">
                                                Detail Inventory Per-Line
                                            </div>

                                            <div class="chart-scroll">

                                                <div id="chartReturnVendor" class="chart-container"></div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- ====================================================== -->
                <!-- VENDOR OVERVIEW -->
                <!-- ====================================================== -->

                <div class="col-lg-4">

                    <div class="dashboard-card h-100">

                        <div class="section-header">
                            <i class="bi bi-people"></i>
                            <span>Vendor Overview</span>
                        </div>

                        <div class="sub-card">

                            <div class="w-100">

                                <div class="sub-title">
                                    Vendor Monitoring
                                </div>

                                <!-- ===================================== -->
                                <!-- ACTIVE VENDOR -->
                                <!-- ===================================== -->

                                <div class="border rounded-3 p-3 mb-3 vendor-kpi-card"
                                    data-kpi="active_vendor">

                                    <div class="small text-muted">
                                        Active Vendor
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">

                                        <div
                                            id="vendorActiveCount"
                                            class="metric-value">
                                            0
                                        </div>

                                        <span
                                            id="vendorActiveBadge"
                                            class="badge bg-secondary">
                                            Idle
                                        </span>

                                    </div>

                                </div>

                                <!-- ===================================== -->
                                <!-- INVENTORY AT VENDOR -->
                                <!-- ===================================== -->

                                <div class="border rounded-3 p-3 mb-3 vendor-kpi-card"
                                    data-kpi="inventory_at_vendor">

                                    <div class="small text-muted">
                                        Outstanding at Vendor
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">

                                        <div
                                            id="vendorInventory"
                                            class="metric-value">
                                            0
                                        </div>

                                        <span
                                            id="vendorInventoryBadge"
                                            class="badge bg-secondary">
                                            Clear
                                        </span>

                                    </div>

                                </div>

                                <!-- ===================================== -->
                                <!-- RETURN ACHIEVEMENT -->
                                <!-- ===================================== -->

                                <div class="border rounded-3 p-3 mb-3 vendor-kpi-card"
                                    data-kpi="return_achievement">

                                    <div class="small text-muted">
                                        Return Achievement
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">

                                        <div
                                            id="vendorAchievement"
                                            class="metric-value">
                                            0%
                                        </div>

                                        <span
                                            id="vendorAchievementBadge"
                                            class="badge bg-secondary">
                                            No Data
                                        </span>

                                    </div>

                                </div>

                                <!-- ===================================== -->
                                <!-- AVERAGE LEAD TIME -->
                                <!-- ===================================== -->

                                <div class="border rounded-3 p-3 mb-3 vendor-kpi-card"
                                    data-kpi="average_lead_time">

                                    <div class="small text-muted">
                                        Average Lead Time
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">

                                        <div
                                            id="vendorLeadTime"
                                            class="metric-value">
                                            0 Days
                                        </div>

                                        <span
                                            id="vendorLeadTimeBadge"
                                            class="badge bg-secondary">
                                            No Data
                                        </span>

                                    </div>

                                </div>

                                <!-- ===================================== -->
                                <!-- OVERDUE VENDOR -->
                                <!-- ===================================== -->

                                <div class="border rounded-3 p-3 vendor-kpi-card"
                                    data-kpi="overdue_vendor">

                                    <div class="small text-muted">
                                        Overdue Vendor
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">

                                        <div
                                            id="vendorOverdueCount"
                                            class="metric-value">
                                            0
                                        </div>

                                        <span
                                            id="vendorOverdueBadge"
                                            class="badge bg-success">
                                            Clear
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

        </section>

        <!-- DETAIL MODAL DASHBOARD WAREHOUSE -->

        <div
            class="modal fade"
            id="dashboardDetailModal"
            tabindex="-1">

            <div class="modal-dialog modal-xl modal-dialog-scrollable">

                <div class="modal-content dashboard-detail-modal">

                    <!-- HEADER -->
                    <div class="modal-header dashboard-modal-header">

                        <div>

                            <h4
                                id="dashboardModalTitle"
                                class="modal-title">
                                Detail Dashboard
                            </h4>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"></button>

                    </div>

                    <!-- BODY -->
                    <div class="modal-body">

                        <div class="table-responsive">

                            <table
                                id="dashboardDetailTable"
                                class="
                table
                dashboard-detail-table
                align-middle
              ">
                            </table>

                        </div>

                    </div>

                    <!-- FOOTER -->
                    <div class="modal-footer dashboard-modal-footer">

                        <button
                            type="button"

                            class="
                  btn
                  btn-secondary
                  px-4
                "

                            data-bs-dismiss="modal">

                            Close
                        </button>

                        <button
                            type="button"
                            id="btnExportDashboardDetail"
                            class="btn btn-success">
                            <i class="bi bi-file-earmark-excel"></i>
                            Export
                        </button>

                    </div>

                </div>

            </div>

        </div>

        <!-- VENDOR KPI DETAIL MODAL -->

        <div
            class="modal fade"
            id="vendorKpiDetailModal"
            tabindex="-1"
            aria-hidden="true">

            <div class="modal-dialog modal-xl modal-dialog-scrollable">

                <div class="modal-content">

                    <div class="modal-header">

                        <div>

                            <h5
                                id="vendorKpiModalTitle"
                                class="modal-title">
                                Vendor Detail
                            </h5>

                            <div
                                id="vendorKpiModalSubtitle"
                                class="small text-muted mt-1">
                            </div>

                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body">

                        <div
                            id="vendorKpiLoading"
                            class="text-center py-5 d-none">

                            <div class="spinner-border text-primary"></div>

                            <div class="mt-2 text-muted">
                                Loading data...
                            </div>

                        </div>

                        <div id="vendorKpiTableContainer">

                            <div class="vendor-kpi-table-scroll">

                                <table
                                    id="vendorKpiDetailTable"
                                    class="
                                        table
                                        table-bordered
                                        table-hover
                                        align-middle
                                        text-nowrap
                                        mb-0
                                    ">

                                    <thead id="vendorKpiTableHead"></thead>

                                    <tbody id="vendorKpiTableBody"></tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary px-4"
                            data-bs-dismiss="modal">

                            Close

                        </button>

                        <button
                            type="button"
                            id="btnExportVendorKpiDetail"
                            class="btn btn-success px-4"
                            disabled>

                            <i class="bi bi-file-earmark-excel"></i>
                            Export

                        </button>

                    </div>

                </div>

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

    <?php include_once __DIR__ . '/../includes/notification.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastEl = document.getElementById('liveToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000
                });
                toast.show();
            }
        });
    </script>

    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));

        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>

    <script>
        var warehouseCharts = {};

        const warehouseChartColor = {
            inventory: '#5f84ad'
        };

        $(document).ready(function() {

            loadWarehouseDashboard();

            setInterval(function() {

                loadWarehouseDashboard();

            }, 60000);

        });

        function loadWarehouseDashboard() {

            $.ajax({

                url: './../config/get_warehouse_dashboard.php',

                type: 'GET',

                dataType: 'json',

                success: function(response) {

                    console.log(response);

                    renderWarehouseSummary(
                        response.ready_transfer.summary, {
                            receiveText: '#rtReceive',
                            outText: '#rtTransfer',
                            inventoryText: '#rtInventory',

                            receiveBar: '#rtBarReceive',
                            outBar: '#rtBarTransfer',
                            inventoryBar: '#rtBarInventory',

                            receiveTooltip: '#rtTooltipReceive',
                            outTooltip: '#rtTooltipTransfer',
                            inventoryTooltip: '#rtTooltipInventory',

                            receiveLabel: 'Receive',
                            outLabel: 'Transfer to Vendor'
                        }
                    );

                    renderWarehouseSummary(
                        response.return_vendor.summary, {
                            receiveText: '#rvReceive',
                            outText: '#rvSendProd',
                            inventoryText: '#rvInventory',

                            receiveBar: '#rvBarReceive',
                            outBar: '#rvBarSendProd',
                            inventoryBar: '#rvBarInventory',

                            receiveTooltip: '#rvTooltipReceive',
                            outTooltip: '#rvTooltipSendProd',
                            inventoryTooltip: '#rvTooltipInventory',

                            receiveLabel: 'Receive',
                            outLabel: 'Send Production'
                        }
                    );

                    renderWarehouseChart(
                        'chartReadyTransfer',
                        '#chartReadyTransferVendor',
                        response.ready_transfer.chart,
                        'ready_transfer'
                    );

                    renderWarehouseChart(
                        'chartReturnVendor',
                        '#chartReturnVendor',
                        response.return_vendor.chart,
                        'return_vendor'
                    );

                    renderVendorOverview(
                        response.vendor_overview
                    );

                    initTooltip();

                },

                error: function(xhr) {

                    console.log(xhr.responseText);

                }

            });

        }

        function initTooltip() {

            $('[data-bs-toggle="tooltip"]').tooltip('dispose');

            document
                .querySelectorAll('[data-bs-toggle="tooltip"]')
                .forEach(function(el) {

                    new bootstrap.Tooltip(el);

                });

        }

        function renderWarehouseSummary(
            data,
            config
        ) {

            let receive =
                parseInt(data.receive) || 0;

            let out =
                parseInt(
                    data.transfer ??
                    data.send_prod
                ) || 0;

            let inventory =
                parseInt(data.inventory) || 0;

            let maxValue = Math.max(
                receive,
                out,
                inventory,
                1
            );

            let receivePercent =
                (receive / maxValue) * 100;

            let outPercent =
                (out / maxValue) * 100;

            let inventoryPercent =
                (inventory / maxValue) * 100;

            $(config.receiveText)
                .text(receive.toLocaleString());

            $(config.outText)
                .text(out.toLocaleString());

            $(config.inventoryText)
                .text(inventory.toLocaleString());

            $(config.receiveBar)
                .css(
                    'width',
                    receivePercent + '%'
                );

            $(config.outBar)
                .css(
                    'width',
                    outPercent + '%'
                );

            $(config.inventoryBar)
                .css(
                    'width',
                    inventoryPercent + '%'
                );

            $(config.receiveTooltip)
                .attr(
                    'data-bs-original-title',
                    config.receiveLabel +
                    ' : ' +
                    receive.toLocaleString() +
                    ' Pairs'
                )
                .attr(
                    'data-value',
                    receive
                );

            $(config.outTooltip)
                .attr(
                    'data-bs-original-title',
                    config.outLabel +
                    ' : ' +
                    out.toLocaleString() +
                    ' Pairs'
                )
                .attr(
                    'data-value',
                    out
                );

            $(config.inventoryTooltip)
                .attr(
                    'data-bs-original-title',
                    'Inventory : ' +
                    inventory.toLocaleString() +
                    ' Pairs'
                )
                .attr(
                    'data-value',
                    inventory
                );

        }

        /* ===================================== */
        /* RENDER VENDOR OVERVIEW */
        /* ===================================== */

        function renderVendorOverview(data) {

            data =
                data ?? {};

            let activeVendor =
                parseInt(
                    data.active_vendor
                ) || 0;

            let inventoryAtVendor =
                parseInt(
                    data.inventory_at_vendor
                ) || 0;

            let returnAchievement =
                parseFloat(
                    data.return_achievement
                ) || 0;

            let averageLeadTime =
                parseFloat(
                    data.average_lead_time
                ) || 0;

            let overdueVendor =
                parseInt(
                    data.overdue_vendor
                ) || 0;

            let slaDays =
                parseInt(
                    data.sla_days
                ) || 4;

            /* ===================================== */
            /* VALUE */
            /* ===================================== */

            $('#vendorActiveCount')
                .text(
                    activeVendor.toLocaleString()
                );

            $('#vendorInventory')
                .text(
                    inventoryAtVendor.toLocaleString() +
                    ' prs'
                );

            $('#vendorAchievement')
                .text(
                    returnAchievement.toLocaleString() +
                    '%'
                );

            $('#vendorLeadTime')
                .text(
                    averageLeadTime.toLocaleString() +
                    ' Days'
                );

            $('#vendorOverdueCount')
                .text(
                    overdueVendor.toLocaleString()
                );

            /* ===================================== */
            /* ACTIVE VENDOR BADGE */
            /* ===================================== */

            updateBadge(
                '#vendorActiveBadge',

                activeVendor > 0 ?
                'Online' :
                'Idle',

                activeVendor > 0 ?
                'bg-success' :
                'bg-secondary'
            );

            /* ===================================== */
            /* INVENTORY BADGE */
            /* ===================================== */

            updateBadge(
                '#vendorInventoryBadge',

                inventoryAtVendor > 0 ?
                'On Process' :
                'Clear',

                inventoryAtVendor > 0 ?
                'bg-warning text-dark' :
                'bg-success'
            );

            /* ===================================== */
            /* ACHIEVEMENT BADGE */
            /* ===================================== */

            let achievementText =
                'Attention';

            let achievementClass =
                'bg-danger';

            if (
                returnAchievement >= 90
            ) {

                achievementText =
                    'Good';

                achievementClass =
                    'bg-success';

            } else if (
                returnAchievement >= 75
            ) {

                achievementText =
                    'Monitor';

                achievementClass =
                    'bg-warning text-dark';

            }

            updateBadge(
                '#vendorAchievementBadge',
                achievementText,
                achievementClass
            );

            /* ===================================== */
            /* LEAD TIME BADGE */
            /* ===================================== */

            updateBadge(
                '#vendorLeadTimeBadge',

                averageLeadTime <= slaDays ?
                'Stable' :
                'Monitor',

                averageLeadTime <= slaDays ?
                'bg-primary' :
                'bg-warning text-dark'
            );

            /* ===================================== */
            /* OVERDUE BADGE */
            /* ===================================== */

            updateBadge(
                '#vendorOverdueBadge',

                overdueVendor > 0 ?
                'Attention' :
                'Clear',

                overdueVendor > 0 ?
                'bg-warning text-dark' :
                'bg-success'
            );

        }

        /* ===================================== */
        /* UPDATE BADGE */
        /* ===================================== */

        function updateBadge(
            selector,
            text,
            className
        ) {

            $(selector)
                .removeClass(
                    'bg-success ' +
                    'bg-secondary ' +
                    'bg-warning ' +
                    'bg-danger ' +
                    'bg-primary ' +
                    'text-dark'
                )
                .addClass(
                    className
                )
                .text(
                    text
                );

        }

        function renderReadyTransferChart(response) {

            let categories =
                response.ready_transfer.chart.categories ?? [];

            let seriesData =
                response.ready_transfer.chart.series ?? [];

            let dynamicChartWidth =
                categories.length * 70;

            dynamicChartWidth =
                Math.max(dynamicChartWidth, 350);

            if (
                warehouseCharts.chartReadyTransfer
            ) {

                warehouseCharts
                    .chartReadyTransfer
                    .destroy();

            }

            var chartOptions = {

                chart: {

                    type: 'bar',

                    height: 280,

                    width: dynamicChartWidth,

                    toolbar: {
                        show: false
                    },

                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 700
                    },

                    events: {

                        dataPointSelection: function(
                            event,
                            chartContext,
                            config
                        ) {

                            let selectedNcvs =
                                categories[
                                    config.dataPointIndex
                                ];

                            let selectedValue =
                                seriesData[
                                    config.dataPointIndex
                                ];

                            if (
                                selectedValue <= 0
                            ) {
                                return;
                            }

                            openWarehouseDashboardDetail({

                                section: 'ready_transfer',

                                type: 'inventory',

                                ncvs: selectedNcvs

                            });

                        }

                    }

                },

                legend: {
                    show: false
                },

                series: [{

                    name: 'Inventory',

                    data: seriesData

                }],

                xaxis: {
                    categories: categories
                },

                colors: [
                    warehouseChartColor.readyTransfer
                ],

                plotOptions: {

                    bar: {

                        borderRadius: 6,

                        columnWidth: '35%'

                    }

                },

                tooltip: {

                    theme: 'light',

                    y: {

                        formatter: function(val) {

                            return val
                                .toLocaleString() +
                                ' prs';

                        }

                    }

                },

                dataLabels: {
                    enabled: true
                },

                grid: {
                    borderColor: '#e2e8f0'
                }

            };

            warehouseCharts.chartReadyTransfer =
                new ApexCharts(

                    document.querySelector(
                        "#chartReadyTransferVendor"
                    ),

                    chartOptions

                );

            warehouseCharts
                .chartReadyTransfer
                .render();

        }

        function renderWarehouseChart(
            chartKey,
            elementId,
            chartData,
            section
        ) {

            let categories =
                chartData.categories ?? [];

            let seriesData =
                chartData.series ?? [];

            let dynamicChartWidth =
                Math.max(
                    categories.length * 90,
                    350
                );

            if (
                warehouseCharts[chartKey]
            ) {

                warehouseCharts[
                    chartKey
                ].destroy();

            }

            let options = {

                chart: {

                    type: 'bar',

                    height: 280,

                    width: dynamicChartWidth,

                    toolbar: {
                        show: false
                    },

                    animations: {

                        enabled: true,

                        easing: 'easeinout',

                        speed: 700

                    },

                    events: {

                        dataPointSelection: function(
                            event,
                            chartContext,
                            config
                        ) {

                            let selectedNcvs =
                                categories[
                                    config.dataPointIndex
                                ];

                            let selectedValue =
                                seriesData[
                                    config.dataPointIndex
                                ];

                            if (
                                selectedValue <= 0
                            ) {
                                return;
                            }

                            openDashboardDetail({

                                section: section,

                                type: 'inventory',

                                ncvs: selectedNcvs

                            });

                        }

                    }

                },

                series: [{

                    name: 'Inventory',

                    data: seriesData

                }],

                colors: [
                    warehouseChartColor.inventory
                ],

                legend: {
                    show: false
                },

                dataLabels: {

                    enabled: true,

                    formatter: function(val) {

                        return val
                            .toLocaleString();

                    }

                },

                xaxis: {
                    categories: categories
                },

                plotOptions: {

                    bar: {

                        borderRadius: 6,

                        columnWidth: '35%'

                    }

                },

                tooltip: {

                    theme: 'light',

                    y: {

                        formatter: function(val) {

                            return val
                                .toLocaleString() +
                                ' Pairs';

                        }

                    }

                },

                grid: {

                    borderColor: '#e2e8f0'

                }

            };

            warehouseCharts[chartKey] =
                new ApexCharts(
                    document.querySelector(
                        elementId
                    ),
                    options
                );

            warehouseCharts[
                chartKey
            ].render();

        }

        /* OPEN MODAL */

        $(document).on(
            'click',
            '.progress-custom',
            function() {

                let value =
                    parseInt(
                        $(this).attr('data-value')
                    ) || 0;

                /* PREVENT ZERO CLICK */

                if (value <= 0) {
                    return;
                }

                let type =
                    $(this).data('type');

                let section =
                    $(this).data('section');

                openDashboardDetail({
                    section: section,
                    type: type
                });

            }
        );

        function openDashboardDetail(params) {

            currentDetailParams = params;

            $.ajax({

                url: './../config/get_warehouse_dashboard_detail.php',

                type: 'GET',

                dataType: 'json',

                data: params,

                success: function(response) {

                    console.log(response);

                    renderDashboardDetailTable(
                        response
                    );

                    $('#dashboardModalTitle')
                        .text('Detail Warehouse Dashboard Monitoring');

                    $('#dashboardModalSubtitle')
                        .text(
                            params.section
                            .replaceAll('_', ' ')
                            .toUpperCase()
                        );

                    $('#dashboardDetailModal')
                        .modal('show');

                }

            });

        }

        /* RENDER DETAIL TABLE */

        function renderDashboardDetailTable(response) {

            /* DESTROY DATATABLE */

            if ($.fn.DataTable.isDataTable(
                    '#dashboardDetailTable'
                )) {

                $('#dashboardDetailTable')
                    .DataTable()
                    .destroy();

            }

            /* DATA */

            let sizes = response.sizes ?? [];
            let rows = response.rows ?? [];

            /* HEADER */

            let headerHtml = `

        <tr>
            <th>NCVS</th>
            <th>Bucket</th>
            <th>Style</th>
            <th>Model</th>
            <th>PO</th>
            <th>PO Item</th>
            <th>Component</th>
    `;

            /* SIZE HEADER */
            sizes.forEach(function(size) {

                headerHtml += `
            <th>${size}</th>
        `;

            });

            /* TOTAL HEADER */

            headerHtml += `

            <th>Total</th>

        </tr>

    `;

            /* BODY */
            let bodyHtml = '';

            rows.forEach(function(row) {

                let totalQty = 0;

                bodyHtml += `

            <tr>

                <td>${row.ncvs ?? ''}</td>
                <td>${row.bucket ?? ''}</td>
                <td>${row.style ?? ''}</td>
                <td>${row.model ?? ''}</td>
                <td>${row.po ?? ''}</td>
                <td>${row.po_item ?? ''}</td>
                <td>${row.component ?? ''}</td>

        `;

                /* SIZE QTY */
                sizes.forEach(function(size) {

                    let qty =
                        row.sizes[size] ?? 0;

                    totalQty += qty;

                    /* EMPTY STYLE */
                    let qtyDisplay =
                        qty > 0 ? qty : '0';

                    let tdClass =
                        qty > 0 ?
                        '' :
                        'empty-size';

                    bodyHtml += `

                <td class="${tdClass}">
                    ${qtyDisplay}
                </td>

            `;

                });

                /* TOTAL */
                bodyHtml += `

                <td class="fw-bold">
                    ${totalQty}
                </td>

            </tr>

        `;

            });

            /* RENDER FULL TABLE */
            $('#dashboardDetailTable')
                .html(

                    `

        <thead>
            ${headerHtml}
        </thead>

        <tbody>
            ${bodyHtml}
        </tbody>

        `

                );

            /* INIT DATATABLE */

            setTimeout(function() {

                let table =
                    $('#dashboardDetailTable')
                    .DataTable({

                        pageLength: 10,

                        lengthMenu: [

                            [10, 15, 20, 50, 100, -1],

                            [10, 15, 20, 50, 100, 'All']

                        ],

                        responsive: false,
                        ordering: false,
                        searching: true,
                        paging: true,
                        info: true,
                        autoWidth: true,
                        language: {
                            search: '',
                            searchPlaceholder: 'Search...'
                        }

                    });

                /* ADJUST */

                table.columns.adjust();

            }, 100);

        }

        /* EXPORT DETAIL */

        $(document).on(
            'click',
            '#btnExportDashboardDetail',
            function() {

                let query =
                    $.param(
                        currentDetailParams
                    );

                window.open(

                    './../config/export_dashboard_detail.php?' +
                    query,

                    '_blank'

                );

            }
        );
    </script>

    <script>
        /* ===================================== */
        /* VENDOR KPI DETAIL */
        /* ===================================== */

        $(document).on(
            'click',
            '.vendor-kpi-card',
            function() {

                const kpi =
                    $(this).attr('data-kpi');

                console.log(
                    'KPI CLICKED =',
                    kpi
                );

                if (!kpi) {

                    console.error(
                        'data-kpi tidak ditemukan pada card'
                    );

                    return;
                }

                openVendorKpiDetail(
                    kpi
                );

            }
        );

        function openVendorKpiDetail(kpi) {

            currentVendorKpi =
                kpi;

            const modalElement =
                document.getElementById(
                    'vendorKpiDetailModal'
                );

            const modal =
                bootstrap.Modal.getOrCreateInstance(
                    modalElement
                );

            $('#vendorKpiLoading')
                .removeClass('d-none');

            $('#vendorKpiTableContainer')
                .addClass('d-none');

            $('#btnExportVendorKpiDetail')
                .prop(
                    'disabled',
                    true
                );

            modal.show();

            $.ajax({

                url: './../config/get_vendor_dashboard_detail.php',

                type: 'GET',

                dataType: 'json',

                data: {
                    kpi: kpi
                },

                success: function(response) {

                    if (
                        response.status !== 'success'
                    ) {

                        alert(
                            response.message ??
                            'Gagal mengambil data'
                        );

                        return;
                    }

                    const rows =
                        response.rows ?? [];

                    $('#vendorKpiModalTitle')
                        .text(
                            response.title ??
                            'Vendor Detail'
                        );

                    $('#vendorKpiModalSubtitle')
                        .text(
                            response.subtitle ??
                            ''
                        );

                    $('#btnExportVendorKpiDetail')
                        .prop(
                            'disabled',
                            rows.length === 0
                        );

                    renderVendorKpiTable(
                        response.columns ?? [],
                        rows
                    );

                },

                error: function(xhr) {

                    console.error(
                        xhr.responseText
                    );

                    alert(
                        'Gagal mengambil detail vendor'
                    );

                },

                complete: function() {

                    $('#vendorKpiLoading')
                        .addClass('d-none');

                    $('#vendorKpiTableContainer')
                        .removeClass('d-none');

                }

            });

        }

        /* ===================================== */
        /* VENDOR KPI TABLE INSTANCE */
        /* ===================================== */

        let vendorKpiTable = null;
        let currentVendorKpi = '';

        /* ===================================== */
        /* RENDER VENDOR KPI TABLE */
        /* ===================================== */

        function renderVendorKpiTable(
            columns,
            rows
        ) {

            if (
                $.fn.DataTable.isDataTable(
                    '#vendorKpiDetailTable'
                )
            ) {

                $('#vendorKpiDetailTable')
                    .DataTable()
                    .destroy();

            }

            let headerHtml =
                '<tr>';

            columns.forEach(
                function(column) {

                    headerHtml += `
        <th>
          ${escapeHtml(column.label)}
        </th>
      `;

                }
            );

            headerHtml +=
                '</tr>';

            $('#vendorKpiTableHead')
                .html(
                    headerHtml
                );

            let bodyHtml = '';

            rows.forEach(
                function(row) {

                    bodyHtml +=
                        '<tr>';

                    columns.forEach(
                        function(column) {

                            const value =
                                row[column.key] ??
                                '';

                            bodyHtml += `
            <td>
              ${escapeHtml(value)}
            </td>
          `;

                        }
                    );

                    bodyHtml +=
                        '</tr>';

                }
            );

            $('#vendorKpiTableBody')
                .html(
                    bodyHtml
                );

            vendorKpiTable =
                $('#vendorKpiDetailTable')
                .DataTable({

                    pageLength: 10,

                    lengthMenu: [
                        [10, 20, 25, 50, -1],
                        [10, 20, 25, 50, 'All']
                    ],

                    searching: true,

                    paging: true,

                    ordering: false,

                    info: true,

                    responsive: false,

                    autoWidth: false,

                    language: {

                        search: '',

                        searchPlaceholder: 'Search...',

                        zeroRecords: 'Data tidak ditemukan',

                        info: 'Showing _START_ to _END_ of _TOTAL_ data',

                        infoEmpty: 'Showing 0 data',

                        paginate: {

                            previous: 'Previous',

                            next: 'Next'

                        }

                    }

                });

        }

        /* ===================================== */
        /* ESCAPE HTML */
        /* ===================================== */

        function escapeHtml(value) {

            return String(
                    value ?? ''
                )
                .replace(
                    /&/g,
                    '&amp;'
                )
                .replace(
                    /</g,
                    '&lt;'
                )
                .replace(
                    />/g,
                    '&gt;'
                )
                .replace(
                    /"/g,
                    '&quot;'
                )
                .replace(
                    /'/g,
                    '&#039;'
                );

        }

        /* ===================================== */
        /* EXPORT VENDOR KPI DETAIL */
        /* ===================================== */

        $(document).on(
            'click',
            '#btnExportVendorKpiDetail',
            function() {

                if (!currentVendorKpi) {

                    alert(
                        'KPI vendor tidak ditemukan'
                    );

                    return;
                }

                const query =
                    $.param({
                        kpi: currentVendorKpi
                    });

                window.location.href =
                    './../config/export_vendor_detail.php?' +
                    query;

            }
        );
    </script>

</body>

</html>