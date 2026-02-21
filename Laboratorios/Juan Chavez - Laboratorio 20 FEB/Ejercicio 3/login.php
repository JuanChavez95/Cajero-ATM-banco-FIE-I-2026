<!DOCTYPE html>
<html lang="en">
<head>

	<!-- All Meta -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="author" content="Juan Carlos CHavez Machaca">
	<meta name="robots" content="">
	<meta name="keywords" content="admin dashboard, admin template, administration, analytics, bootstrap, cafe admin, elegant, food, health, kitchen, modern, responsive admin dashboard, restaurant dashboard">
	<meta name="description" content="Discover Davur - the ultimate admin dashboard and Bootstrap 5 template. Specially designed for professionals, and for business. Davur provides advanced features and an easy-to-use interface for creating a top-quality website with frontend">
	<meta property="og:title" content="Davur : Restaurant Admin Dashboard + FrontEnd">
	<meta property="og:description" content="Discover Davur - the ultimate admin dashboard and Bootstrap 5 template. Specially designed for professionals, and for business. Davur provides advanced features and an easy-to-use interface for creating a top-quality website with frontend">
	<meta property="og:image" content="https://davur.dexignzone.com/dashboard/social-image.png">
	<meta name="format-detection" content="telephone=no">
	
	<!-- Mobile Specific -->
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<!-- Title -->
	<title>Loguin</title>
    
	<!-- Favicon icon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
	<!-- Stylesheet -->
	<link href="assets/vendor/animate/animate.css" rel="stylesheet">
	
	<!-- Custom Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
	<link class="skin" rel="stylesheet" href="assets/css/skin/skin-1.css">	
	
	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
	
</head>
<body id="bg">

<!-- Preloading  -->
<div id="loading-area" class="loading-page-1">
	<div class="loader"></div>
</div>

<div class="page-wraper">

	<!-- Header -->
	<header class="site-header mo-left header ">
		<!-- Main Header -->
		<div class="sticky-header main-bar-wraper navbar-expand-lg">
			<div class="main-bar clearfix ">
				<div class="container clearfix">
	
					<!-- Website Logo -->
					<div class="logo-header logo-dark">
						<a href="index.html"><img src="assets/images/logo.png" alt=""></a>
					</div>
					
					<!-- Nav Toggle Button -->
					<button class="navbar-toggler collapsed navicon justify-content-end" type="button"
					data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
						<span></span>
						<span></span>
						<span></span>
					</button>
					
					<!-- Extra Nav -->
					<div class="extra-nav">
						<div class="extra-cell">
							<h6 class="phone"><span><i class="fa-solid fa-phone"></i></span>(+91) 987 654 3210</h6>	
							<a class="btn btn-primary btn-lg" href="contact-us.html">Contactos</a>
						</div>
					</div>
									
					<!-- Header Nav -->
					<div class="header-nav navbar-collapse collapse " id="navbarNavDropdown">
						<div class="logo-header">
							<a href="index.html" class="logo-dark"><img src="assets/images/logo.png" alt=""></a>
						</div>
						<ul class="nav navbar-nav navbar navbar-left">	
							<li><a href="index.html">Home</a></li>
							<li><a href="about-us.html">About Us</a></li>
							<li class="sub-menu-down"><a href="javascript:void(0);">Shop</a>
								<ul class="sub-menu">
									<li><a href="shop-grid-view.html">Shop Grid</a></li>					
									<li><a href="shop-detail.html">Shop Detail</a></li>			
									<li><a href="shop-cart.html">Shop Cart</a></li>
									<li><a href="shop-checkout.html">Shop Checkout</a></li>
									<li><a href="wishlist.html">Wishlist</a></li>
									<li><a href="shop-login.html">Login</a></li>		
									<li><a href="shop-registration.html">Registration</a></li>
								</ul>
							</li>
							<li class="sub-menu-down"><a href="javascript:void(0);">Blog</a>
								<ul class="sub-menu">
									<li><a href="blog-list.html">Blog List</a></li>
									<li><a href="blog-grid.html">Blog Grid</a></li>
									<li><a href="blog-details.html">Blog Details</a></li>
								</ul>
							</li>
							<li><a href="contact-us.html">Contact Us</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<!-- Main Header End -->
	</header>
	<!-- Header End -->
	
	<div class="page-content">
		<!-- Banner  -->
		<div class="dz-bnr-inr dz-bnr-inr-sm text-center overlay-gradient-dark" style="background-image: url(assets/images/banner/bnr1.jpg);">
			<div class="container">
				<div class="dz-bnr-inr-entry">
					<h1>USB Login</h1>
					<!-- Breadcrumb Row -->
					<nav aria-label="breadcrumb" class="breadcrumb-row">
						<ul class="breadcrumb">
							<li class="breadcrumb-item"><a href="index.html">Home</a></li>
							<li class="breadcrumb-item active" aria-current="page">Shop Login</li>
						</ul>
					</nav>
					<!-- Breadcrumb Row End -->
				</div>
			</div>
		</div>
		<!-- Banner End -->
		
			<!-- contact area -->
			<section class="content-inner shop-account">
				<!-- Product -->
				<div class="container">
					<div class="row">
						<div class="col-lg-6 col-md-6 mb-4">
							<div class="login-area">
								<div class="tab-content">
									<h4>Sea Bienvenido</h4>
									<p><small>Librería implementada por Juan Carlos Chavez Machaca de la carrera de Ingeniería de Sistemas - Séptimo Semestre de la Universidad Salesiana de Bolivia.</small></p>
									<a class="btn btn-primary btnhover m-r5 button-lg radius-no" href="shop-registration.html">Crea tu cuenta</a>
								</div>
							</div>
						</div>
						<div class="col-lg-6 col-md-6 mb-4">
							<div class="login-area">
								<div class="tab-content nav">
									<form id="loginForm" class="tab-pane active col-12">
										<h4 class="m-b5">Login</h4>
										<small class="font-weight-600 m-b20 d-block">Por favor ingrese sus datos correctos.</small>
										<div class="mb-4">
											<label class="label-title">E-MAIL *</label>
											<input id="email" required class="form-control" placeholder="Ingresa tu Email" type="email">
										</div>
										<div class="mb-4">
											<label class="label-title">PASSWORD *</label>
											<input id="password" required class="form-control" placeholder="Ingresa tu contraseña" type="password">
										</div>
										<div class="text-left">
											
											<button type="button" id="btn-login" class="btn btn-primary me-2">Login</button>
											<a data-bs-toggle="tab" href="#forgot-password" class="m-l5"><i class="fas fa-unlock-alt"></i> Forgot Password</a> 
										</div>
									</form>
									<form id="forgot-password" class="tab-pane fade  col-12">
										<h4 class="m-b5">Forget Password ?</h4>
										<small class="font-weight-600 m-b20 d-block">We will send you an email to reset your password. </small>
										<div class="mb-3">
											<label class="label-title">E-MAIL *</label>
											<input name="dzName" required="" class="form-control" placeholder="Your Email Id" type="email">
										</div>
										<div class="text-left"> 
											<a class="btn btn-outline-secondary m-r10" data-bs-toggle="tab" href="#login">Back</a>
											<button class="btn btn-primary">Submit</button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
			<!-- Product END -->

	</div>

	<!-- Footer -->
    <footer class="site-footer style-1">
		<div class="footer-top">
			<div class="container">
				<div class="footer-subscribe-wrapper text-lg-start text-center">
					<div class="row align-items-center">
						<div class="col-lg-6 col-md-12 col-sm-12">
							<div class="widget wow fadeInUp" data-wow-delay="0.1s">	
								<div class="footer-logo logo-light">
									<a href="index.html"><img src="assets/images/logo-white.png" alt=""></a>
								</div>
								<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>
							</div>	
						</div>
						<div class="col-lg-6 col-md-12 col-sm-12">
							<div class="widget wow fadeInUp" data-wow-delay="0.2s">
								<h4 class="footer-title">Subscribe To Our Newsletter</h4>
								<form class="dzSubscribe ft-subscribe mb-4" action="assets/script/mailchamp.php" method="post">
									<div class="dzSubscribeMsg"></div>
									<div class="input-group mb-0"> 
										<input name="dzEmail" required="required" type="email" class="form-control" placeholder="Enter Your Email">
										<button name="submit" value="Submit" type="submit" class="btn btn-primary style-1 ">Subscribe</button>				
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
		
				<div class="row text-lg-start text-center">
					<div class="col-lg-6 col-md-12 col-sm-12">
						<div class="widget widget_links wow fadeInUp" data-wow-delay="0.3s">
							<h4 class="footer-title">OUR LINKS</h4>
							<ul>
								<li><a href="javascript:void(0);">Home</a></li>
								<li><a href="javascript:void(0);">About Us</a></li>
								<li><a href="javascript:void(0);">Services</a></li>
								<li><a href="javascript:void(0);">Team</a></li>
								<li><a href="javascript:void(0);">Blog</a></li>
							</ul>
						</div>
					</div>
					<div class="col-lg-6 col-md-12 col-sm-12">
						<div class="widget widget_links wow fadeInUp text-lg-end " data-wow-delay="0.4s">
							<h4 class="footer-title">OTHER LINKS</h4>
							<ul class="justify-content-lg-end">
								<li><a href="javascript:void(0);">FAQ</a></li>
								<li><a href="javascript:void(0);">Portfolio</a></li>
								<li><a href="javascript:void(0);">Privacy Policy</a></li>
								<li><a href="javascript:void(0);">Terms & Condition</a></li>
								<li><a href="javascript:void(0);">Support</a></li>
							</ul>
						</div>
					</div>
				</div>	
			</div>
		</div>
		<div class="footer-bottom">
			<div class="container">
				<div class="footer-inner">
					<div class="row align-items-center">
						<div class="col-lg-6 col-md-6 col-sm-12 text-lg-start text-md-start text-center">
							<p class="copyright-text wow fadeInUp" data-wow-delay="0.5s">Copyright 2023 All right reserved.</p>
						</div>
						<div class="col-lg-6 col-md-6 col-sm-12 text-lg-end text-md-end text-center">
							<p class="copyright-text wow fadeInUp" data-wow-delay="0.6s">Powered by<a class="text-primary" href="https://dexignzone.com/" target="_blank"> Dexignzone</a></p>
						</div>
					</div>
				</div>	
			</div>
		</div>
		<img src="assets/images/footer/pic1.png" class="bg1" alt="image">
		<img src="assets/images/footer/pic2.png" class="bg2" alt="image">		
	</footer>
    <!-- Footer End -->

	<button class="scroltop icon-up" type="button"><i class="fas fa-arrow-up"></i></button>
	
</div>
<!-- JAVASCRIPT FILES ========================================= -->
<script src="assets/js/jquery.min.js"></script><!-- JQUERY.MIN JS -->
<script src="assets/vendor/wow/wow.js"></script><!-- WOW.JS -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script><!-- WOW.JS -->
<script src="assets/js/dz.ajax.js"></script><!-- AJAX -->
<script src="assets/js/custom.js"></script><!-- CUSTOM JS -->

<script>
$('#btn-login').click(function() {

    $.ajax({
        url: "validar.php",
        type: "POST",
        data: {
            email: $('#email').val(),
            password: $('#password').val()
        },
        success: function(response) {
            if (response == "success") {
                window.location = "../Dashboard/index.html";
            } else {
                alert("Usuario o contraseña incorrectos");
            }
        }
    });

});
</script>

</body>
</html>