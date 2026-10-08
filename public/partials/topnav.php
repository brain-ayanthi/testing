<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">



<?php
if(!defined('BASE_URL')) exit; // simple guard
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/dashboard.php">TeaLeaf C/M/System</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="farmers.php">Farmers</a></li>
        <li class="nav-item"><a class="nav-link" href="collections.php">Collections</a></li>
       
       
	    <!-- Dropdown Menu -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
           Deduction
          </a>
          <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
            <li><a class="dropdown-item" href="tea_bag_issues_list.php">Tea Bag Issue</a></li>
			 <li><a class="dropdown-item" href="advance_issue_list.php">Advance Issue</a></li>
            <li><a class="dropdown-item" href="festival_advance_list.php">Festival Advance</a></li>
          <li><a class="dropdown-item" href="chemical_issue_list.php">Chemical Issue</a></li>
		  <li><a class="dropdown-item" href="manure_issues_list.php">Manure</a></li>
		  <li><a class="dropdown-item" href="others_list.php">Others</a></li>
		  </ul>
        </li>
	   
	   
	   <!-- Dropdown Menu -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
           Leaf Distribution
          </a>
          <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
            <li><a class="dropdown-item" href="daily_distribution.php">Factory Distribution</a></li>
            <li><a class="dropdown-item" href="leaf_distribution_records.php">Factory Distribution List</a></li>
          </ul>
        </li>



	   <!-- Dropdown Menu -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Settings
          </a>
          <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
            <li><a class="dropdown-item" href="rates.php">Rates Add</a></li>
            <li><a class="dropdown-item" href="areas.php">Areas Add</a></li>
		<li><a class="dropdown-item" href="factory_list.php">Factory Add</li>
			<li><a class="dropdown-item" href="employees.php">Employees Add</li>
			
          </ul>
        </li>
		

        <li class="nav-item"><a class="nav-link" href="payments.php">Payments</a></li>
       
 <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
           Reports
          </a>
          <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
            <li><a class="dropdown-item" href="reports.php">Collection Report</a></li>
            <li><a class="dropdown-item" href="wight.php">Month Weight Report</a></li>
			 <li><a class="dropdown-item" href="Pay_Detiles.php">Month Payment Details Report</a></li>
           <li><a class="dropdown-item" href="Deduction.php">Deduction Report</a></li>
         
		  </ul>
        </li>



	  </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="#">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
