/* JavaScript Document */
jQuery(window).on('load', function() {
    'use strict';
	
	// service-silder-swiper
	if(jQuery('.main-swiper').length > 0){
		var mainSwiper = new Swiper(".main-swiper", {
			effect: "fade",
			speed: 1000,
			loop:true,
			slidesPerView: 1,
			autoplay: {
				delay: 150000000,
			},
			pagination: {
				el: ".main-swiper1-pagination",
				clickable: true,
				renderBullet: function (index, className) {
					return '<span class="' + className + '">' +"0"+ (index + 1) + "</span>";
				},
			},
		});
		
		mainSwiper.on('slideChange', function () {
			setTimeout(function(){
				var dataRel = $('.main-swiper .swiper-slide-active').data('rel');	
				jQuery('.main-thumb-wrapper li').removeClass('active');
				jQuery('.main-thumb-wrapper li[data-member='+dataRel+']').addClass('active');
			}, 500);
		});
		
		jQuery('.main-thumb-wrapper li a').on('click',function(e){
			e.preventDefault();
			var memberRel = jQuery(this).parent().data('member');
			mainSwiper.slideTo(memberRel);
		});
	}
	
	// product Swiper
	if(jQuery('.product-swiper').length > 0){
		var swiper = new Swiper('.product-swiper', {
			speed: 1500,
			parallax: true,
			slidesPerView: 4,
			spaceBetween: 30,
			loop:true,
			autoplay: {
			   delay: 3000,
			},
			navigation: {
				nextEl: '.product-swiper-next',
				prevEl: '.product-swiper-prev',
			},
			breakpoints: {
				1280: {
					slidesPerView: 4,
				},
				1024: {
					slidesPerView: 3,
				},
				591: {
					slidesPerView: 2,
				},
				320: {
					slidesPerView: 1,
				},
			}

		});
	}
	
	// product Swiper
	if(jQuery('.blog-swiper').length > 0){
		var swiper = new Swiper('.blog-swiper', {
			loop:true,
			speed: 1500,
			parallax: true,
			slidesPerView: 3,
			centeredSlides: true,
			spaceBetween: 30,
			autoplay: {
			   delay: 3000,
			},
			navigation: {
				nextEl: '.blog-swiper-next',
				prevEl: '.blog-swiper-prev',
			},
			breakpoints:{
				1024: {
					slidesPerView: 3,
				},
				767: {
					slidesPerView: 2,
				},
				320: {
					slidesPerView: 1,
				},
			}
		});
	}
	
	// testimonial2
	if(jQuery('.testimonial-swiper').length > 0){
		var swiper = new Swiper('.testimonial-swiper', {
			loop:true,
			speed: 2000,
			parallax: true,
			slidesPerView: 1,
			spaceBetween: 30,
			centeredSlides: true,
			autoplay: {
			   delay: 3000,
			}, 
			
			navigation: {
				nextEl: '.testi-swiper-next',
				prevEl: '.testi-swiper-next',
			},
			breakpoints: {
				1200: {
					slidesPerView: 1,
				},
				768: {
					slidesPerView: 1,
				},
				320: {
					slidesPerView: 1,
				},
			}
		});
	}
	
	
});
/* Document .ready END */