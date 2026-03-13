<footer>
 <p>  Stock Management System</p>
 <?php
	 // Hide learning journal on customer-facing dashboards
	 if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
 ?>
 <?php } ?>
</footer>