 <!-- ======= Header ======= -->
 <header id="header" class="header fixed-top d-flex align-items-center">

   <div class="d-flex align-items-center justify-content-between">
     <a href="./../pages/index.php" class="logo d-flex align-items-center">
       <img src="../assets/img/Logo-Stg.png" alt="">
       <img src="../assets/img/isubcont.png" alt="" style="width: 90px; height: 110px;">
     </a>
     <i class="bi bi-list toggle-sidebar-btn"></i>
   </div><!-- End Logo -->

   <nav class="header-nav ms-auto">
     <ul class="d-flex align-items-center">


       <li class="nav-item dropdown pe-3">
         <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
           <img src="../assets/img/profile-user.png" alt="Profile" class="rounded-circle border border-2 border-primary shadow-sm" width="40" height="40">
           <span class="d-none d-md-block dropdown-toggle ps-2 fw-semibold text-dark">
             <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
           </span>
         </a><!-- End Profile Image Icon -->

         <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile shadow-lg rounded-3 border-0">
           <!-- Header -->
           <li class="dropdown-header text-center">
             <img src="../assets/img/profile-user.png" alt="Profile" class="rounded-circle mb-2 shadow" width="60" height="60">
             <h6 class="mb-0">
               <?php echo htmlspecialchars($_SESSION['nik_user'] ?? ''); ?> |
               <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
             </h6>
             <small class="text-muted">
               <?php echo htmlspecialchars($_SESSION['role_name'] ?? ''); ?>
             </small>
           </li>

           <li>
             <hr class="dropdown-divider">
           </li>
           <li>
             <hr class="dropdown-divider">
           </li>

           <!-- Logout -->
           <li>
             <a class="dropdown-item d-flex align-items-center text-danger fw-semibold" href="../logout.php">
               <i class="bi bi-box-arrow-right me-2"></i>
               <span>Log Out</span>
             </a>
           </li>
         </ul><!-- End Profile Dropdown Items -->
       </li><!-- End Profile Nav -->



     </ul>
   </nav><!-- End Icons Navigation -->

 </header><!-- End Header -->

 <!-- ======= Sidebar ======= -->
 <aside id="sidebar" class="sidebar">
   <ul class="sidebar-nav" id="sidebar-nav">
     <?php
      // ambil role
      $role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;

      // halaman aktif
      $page = $page ?? '';

      // ambil menu sesuai role (hanya yang allowed)
      $sql = "SELECT m.* 
          FROM menus m
          JOIN role_permissions rp ON rp.menu_id = m.id
          WHERE rp.role_id = '{$role_id}' AND rp.allowed = 1
          ORDER BY COALESCE(m.parent_id, 0), m.ordering ASC";
      $res = mysqli_query($conn, $sql);

      $menusByParent = [];
      $menusById = [];
      while ($r = mysqli_fetch_assoc($res)) {
        $r['id'] = (int)$r['id'];
        $r['parent_id'] = is_null($r['parent_id']) || $r['parent_id'] === '' ? 0 : (int)$r['parent_id'];
        $menusByParent[$r['parent_id']][] = $r;
        $menusById[$r['id']] = $r;
      }

      // cari menu aktif
      $activeMenuId = null;
      foreach ($menusById as $id => $m) {
        if (!empty($m['key_name']) && $m['key_name'] === $page) {
          $activeMenuId = $id;
          break;
        }
      }

      // trace parent chain biar parent menu bisa auto expand
      $openIds = [];
      if ($activeMenuId) {
        $cursor = $activeMenuId;
        while (!empty($menusById[$cursor]['parent_id'])) {
          $parent = (int)$menusById[$cursor]['parent_id'];
          $openIds[$parent] = true;
          $cursor = $parent;
        }
      }

      function renderMenuTree($parent_id, $menusByParent, $menusById, $activeMenuId, $openIds)
      {
        if (empty($menusByParent[$parent_id])) return;

        foreach ($menusByParent[$parent_id] as $menu) {
          $id = (int)$menu['id'];
          $hasChild = !empty($menusByParent[$id]);
          $isActive = ($activeMenuId === $id);
          $isOpen   = isset($openIds[$id]);

          $icon = $menu['icon'] ?: ($hasChild ? 'bi bi-folder' : 'bi bi-circle');

          echo '<li class="nav-item">';

          if ($hasChild) {
            echo '<a class="nav-link ' . ($isOpen ? '' : 'collapsed') . '" data-bs-target="#menu-' . $id . '" data-bs-toggle="collapse" href="#">';
            echo '<i class="' . htmlspecialchars($icon) . '"></i><span>' . htmlspecialchars($menu['name']) . '</span>';
            echo '<i class="bi bi-chevron-down ms-auto"></i></a>';

            echo '<ul id="menu-' . $id . '" class="nav-content collapse ' . ($isOpen ? 'show' : '') . '" data-bs-parent="#sidebar-nav">';
            renderMenuTree($id, $menusByParent, $menusById, $activeMenuId, $openIds);
            echo '</ul>';
          } else {
            echo '<a class="nav-link ' . ($isActive ? 'active' : '') . '" href="' . htmlspecialchars($menu['url']) . '">';
            echo '<i class="' . htmlspecialchars($icon) . '"></i><span>' . htmlspecialchars($menu['name']) . '</span></a>';
          }

          echo '</li>';
        }
      }

      renderMenuTree(0, $menusByParent, $menusById, $activeMenuId, $openIds);
      ?>
   </ul>
 </aside><!-- End Sidebar -->