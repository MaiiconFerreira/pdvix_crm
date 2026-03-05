<!--begin::Header-->
<nav class="app-header navbar navbar-expand bg-body">
  <!--begin::Container-->
  <div class="container-fluid">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
          <i class="bi bi-list"></i>
        </a>
      </li>
      <li class="nav-item d-md-block">
        <a href="#" onclick="history.back();" class="nav-link">
          <i class="bi bi-arrow-return-left"></i>
        </a>
      </li>
    </ul>
    <!--end::Start Navbar Links-->

    <!--begin::End Navbar Links-->
    <ul class="navbar-nav ms-auto">

    


      <!--begin::User Menu Dropdown-->
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
          <img
            src="../template/dist/assets/img/icone.png"
            class="user-image rounded-circle shadow"
            id="user-avatar-img"
            alt="User Image"
          />
          <span class="d-none d-md-inline"><?php echo $nome; ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <li class="user-footer">
            <a href="../logout" class="btn btn-default btn-flat float-end">Sair</a>
          </li>
        </ul>
      </li>
      <!--end::User Menu Dropdown-->

    </ul>
    <!--end::End Navbar Links-->
  </div>
  <!--end::Container-->
</nav>
<!--end::Header-->