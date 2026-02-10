<?php
/**
 * VIP Landing Page Handler
 * 
 * Provides a shortcode [vip_landing_page] to display the Elite White Glove landing page
 * with integrated VIP form.
 * 
 * @package OnRoute_Courier_Booking
 */

if ( !defined('ABSPATH')) {
    exit;
}

class OnRoute_VIP_Landing_Page {

    /**
     * Constructor
     */
    public function __construct() {
        // Landing page shortcode
        add_shortcode('vip_landing_page', array($this, 'render_landing_page'));
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Enqueue Styles
     */
    public function enqueue_styles() {
        wp_enqueue_style('tailwindcss', 'https://cdn.tailwindcss.com?plugins=forms,container-queries');
        wp_enqueue_style('google-fonts-manrope', 'https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap');
        wp_enqueue_style('google-fonts-playfair', 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap');
        wp_enqueue_style('material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        wp_enqueue_script('font-awesome-js', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js', array(), '6.4.0', true);
    }

    /**
     * Render VIP Landing Page
     */
    public function render_landing_page($atts) {
        ob_start();
        
        $site_url = home_url();
        ?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Elite White Glove Courier &amp; Logistics | Bespoke VIP Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "charcoal": "#121212",
                        "mahogany": "#2d1b1b",
                        "gold": "#c5a059",
                        "gold-muted": "#8a703f",
                        "earth": "#1a1a1a",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"],
                        "serif": ["Playfair Display", "serif"]
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        :root {
            --primary: #c5a059;
            --mahogany-deep: #221414;
            --bg-deep: #0d0d0d;
        }
        body {
            font-family: 'Manrope', sans-serif;
            background-color: var(--bg-deep);
            color: #e5e5e5;
        }
        .serif-text {
            font-family: 'Playfair Display', serif;
        }
        .hero-overlay {
            background: linear-gradient(to bottom, rgba(13, 13, 13, 0.4) 0%, rgba(13, 13, 13, 0.9) 100%);
        }
        .glass-nav {
            background: rgba(18, 18, 18, 0.85);
            backdrop-filter: blur(10px);
        }
        
        /* WordPress Admin Bar Compatibility */
        .admin-bar .glass-nav {
            top: 32px !important;
        }
        @media screen and (max-width: 782px) {
            .admin-bar .glass-nav {
                top: 46px !important;
            }
        }
        
        /* Button Styles - Royal Feel Only */
        button:focus-visible {
            outline: none !important;
        }
        
        button:active {
            transform: none !important;
            color: inherit !important;
            background-color: inherit !important;
            border-color: inherit !important;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        button:focus {
            outline: none !important;
            box-shadow: none !important;
            color: inherit !important;
            background-color: inherit !important;
        }

        /* Explicitly kill Pink/Childish colors across the whole page */
        * {
            -webkit-tap-highlight-color: transparent !important;
            outline-color: #c5a059 !important;
        }

        /* Hover States - Force visibility */
        .btn-gold:hover {
            background-color: #ffffff !important;
            color: #121212 !important;
            border-color: #ffffff !important;
        }
        
        button:hover {
            text-decoration: none !important;
        }

        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border-color: #ffffff !important;
        }
        
        /* Ensure no global styles turn our hover text white on white bg */
        .btn-gold:hover * {
            color: #121212 !important;
        }
        
        button {
            -webkit-tap-highlight-color: transparent;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        /* Override all default button styles */
        button::-moz-focus-inner {
            border: none !important;
            padding: 0 !important;
        }
        
        /* Professional Animation for Icons */
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        
        @keyframes iconPulse {
            0%, 100% { color: #c5a059; opacity: 1; }
            50% { color: #e8d5c0; opacity: 0.8; }
        }
        
        .animate-icon {
            animation: iconFloat 3s ease-in-out infinite;
        }
        
        .icon-pulse {
            animation: iconPulse 2s ease-in-out infinite;
        }
        
        /* Card Hover Effects */
        .feature-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .feature-card:hover {
            transform: translateY(-4px);
        }
        
        .feature-card:hover .feature-icon {
            color: #e8d5c0;
            transform: scale(1.15) rotate(-5deg);
        }
        
        .feature-icon {
            transition: all 0.3s ease;
        }
        
        /* Ideal For Grid Animation */
        .ideal-item {
            transition: all 0.3s ease;
        }
        
        .ideal-item:hover {
            transform: translateX(6px);
        }
        
        .ideal-item:hover .ideal-icon {
            color: #e8d5c0;
            transform: scale(1.2);
        }
        
        .ideal-icon {
            transition: all 0.3s ease;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 600px) {
            .vip-card {
                padding: 18px 15px;
            }
            .vip-form-title {
                font-size: 20px;
            }
            .vip-success-content {
                max-width: 85%;
                padding: 35px 25px;
            }
            .vip-success-title {
                font-size: 24px;
            }
            .hero-section-text {
                font-size: 50px !important;
            }
        }
    </style>
</head>
<body class="bg-charcoal text-slate-200">

<header class="fixed top-0 z-50 w-full glass-nav border-b border-white/5 px-6 md:px-12 py-5">
    <div class="mx-auto flex max-w-[1600px] items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="flex flex-col">
                <h2 class="text-sm md:text-base font-bold tracking-[0.2em] text-white uppercase leading-none">Elite White Glove</h2>
                <span class="text-[9px] md:text-[10px] uppercase tracking-[0.4em] text-gold/70 mt-1">Courier &amp; Logistics</span>
            </div>
        </div>
        <nav class="hidden lg:flex items-center gap-10">
            <a class="text-[10px] font-bold uppercase tracking-widest text-white/50 hover:text-gold transition-colors" href="#">Operations</a>
            <a class="text-[10px] font-bold uppercase tracking-widest text-white/50 hover:text-gold transition-colors" href="#">Asset Tracking</a>
            <a class="text-[10px] font-bold uppercase tracking-widest text-white/50 hover:text-gold transition-colors" href="#">Compliance</a>
            <a class="text-[10px] font-bold uppercase tracking-widest text-white/50 hover:text-gold transition-colors" href="#">Global Network</a>
        </nav>
        <div class="flex items-center gap-6">
            <button class="btn-outline border border-gold/40 px-6 py-2.5 text-[10px] font-bold uppercase tracking-[0.2em] text-gold transition-all" title="Access Your Account">
                Verified Login
            </button>
        </div>
    </div>
</header>

<main>
    <section class="relative min-h-[95vh] w-full flex items-center overflow-hidden pt-20">
        <div class="absolute inset-0 z-0 bg-cover bg-no-repeat" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuA4CYzcn6fbkK6klpWFGEpu-ckBpDFbA2P-BfuYtMlsS_QZz4WRhdhOm4BXQvTiqwKyyPOd8VS8kdBgchdA_PmS_a1IplUWPVbq8h_vBsfuEWzZaI8mCtuiy3MEygD4X5hSj-KCs3PEN_RX12UNUwxuKZcuf92XitlWx6usL9C2yVHytaYznxGeKA0APwzHwx0YX6U8IpG3gOEQ5nxp02_Lc_186OxLkChc90c3jI4jQoZ_dzaQkMU1wJvjBrBw_siSzIBsOY6V0bQ'); background-position: center 30%;">
            <div class="hero-overlay absolute inset-0"></div>
        </div>
        <div class="relative z-10 mx-auto max-w-[1600px] w-full px-6 md:px-12 text-center">
            <div class="max-w-4xl mx-auto">
                <span class="mb-6 inline-flex items-center gap-3 text-[14px] font-bold uppercase tracking-[0.5em] text-gold justify-center">
                    <span class="h-px w-8 bg-gold"></span> Bespoke Client Portal <span class="h-px w-8 bg-gold"></span>
                </span>
                <h1 class="serif-text mb-8 text-5xl md:text-8xl font-normal leading-[1.1] text-white hero-section-text">
                    Elite White Glove <br/>
                    <span class="italic text-gold">Courier &amp; Logistics</span>
                </h1>
                <p class="mb-12 max-w-2xl mx-auto text-lg font-light leading-relaxed tracking-wide text-white/70">
                    A bespoke portal for verified clients requiring discreet, high-priority asset movement with absolute precision and efficient information retrieval.
                </p>
                <div class="flex flex-wrap gap-6 justify-center">
                    <button class="btn-gold bg-gold px-12 py-5 text-[10px] font-black uppercase tracking-[0.3em] text-charcoal transition-all border border-gold" title="Request Secure Vetting">
                        Request Secure Vetting
                    </button>
                    <button class="btn-outline border border-white/20 bg-white/5 px-12 py-5 text-[10px] font-bold uppercase tracking-[0.3em] text-white transition-all" title="View Service Protocols">
                        Service Protocols
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="border-y border-white/5 bg-mahogany py-6">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-6 px-6 md:flex-row md:gap-12">
            <div class="flex items-center gap-4">
                <span class="text-[11px] font-black tracking-[0.3em] uppercase text-gold">Affiliated with Scope Security Oxford</span>
            </div>
            <div class="hidden h-4 w-px bg-white/10 md:block"></div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-gold text-sm">verified_user</span>
                <p class="text-[10px] font-medium uppercase tracking-[0.2em] text-white/40">
                    Full Tier-1 Tactical Security Integration
                </p>
            </div>
        </div>
    </div>

    <section class="py-32 px-6 md:px-12 bg-charcoal">
        <div class="mx-auto max-w-5xl">
            <!-- Service Overview Section -->
            <div class="mb-20 text-center">
                <div class="inline-flex items-center gap-3 mb-6 justify-center">
                    <span class="h-px w-6 bg-gold"></span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.4em] text-gold">Service Excellence</span>
                    <span class="h-px w-6 bg-gold"></span>
                </div>
                <h2 class="serif-text text-4xl md:text-5xl font-bold text-white mb-8">Service Overview</h2>
                <div class="bg-gradient-to-r from-mahogany/20 via-mahogany/10 to-transparent border-l-2 border-gold/50 pl-8 py-6 mx-auto max-w-2xl">
                    <p class="text-base md:text-lg font-light leading-relaxed text-white/80">
                        Our VIP White Glove Service is a bespoke, enquiry-only courier solution for high-value, sensitive, urgent, or mission-critical deliveries where standard courier services are not suitable.
                    </p>
                </div>
            </div>

            <!-- What Makes Our VIP Service Different -->
            <div class="mb-28 pt-16 border-t border-gold/10">
                <div class="inline-flex items-center gap-3 mb-6 justify-center w-full">
                    <span class="h-px w-6 bg-gold"></span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.4em] text-gold">Distinctive Advantages</span>
                    <span class="h-px w-6 bg-gold"></span>
                </div>
                <h2 class="serif-text text-4xl md:text-5xl font-bold text-white mb-16 text-center">What Makes Our VIP Service Different</h2>
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Feature Card 1 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">Dedicated Courier</h3>
                        <p class="text-white/60 text-sm leading-relaxed">From collection to delivery – one professional handling your entire shipment</p>
                    </div>
                    
                    <!-- Feature Card 2 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon" style="animation-delay: 0.3s;">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">No Multi-Drop Routes</h3>
                        <p class="text-white/60 text-sm leading-relaxed">Direct priority delivery – your shipment is the only focus</p>
                    </div>
                    
                    <!-- Feature Card 3 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon" style="animation-delay: 0.6s;">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">Enhanced Care</h3>
                        <p class="text-white/60 text-sm leading-relaxed">For high-value, confidential, or fragile items requiring special handling</p>
                    </div>
                    
                    <!-- Feature Card 4 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon" style="animation-delay: 0.9s;">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">Security Available</h3>
                        <p class="text-white/60 text-sm leading-relaxed">Qualified SIA personnel available through Scope Security Oxford</p>
                    </div>
                    
                    <!-- Feature Card 5 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon" style="animation-delay: 1.2s;">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">Professional Standards</h3>
                        <p class="text-white/60 text-sm leading-relaxed">Uniformed, trained couriers meeting the highest service standards</p>
                    </div>
                    
                    <!-- Feature Card 6 -->
                    <div class="feature-card group bg-gradient-to-br from-white/[0.03] to-transparent border border-gold/10 p-8 rounded hover:border-gold/30 text-center">
                        <div class="flex flex-col items-center gap-4 mb-4">
                            <span class="material-symbols-outlined text-4xl text-gold feature-icon animate-icon" style="animation-delay: 1.5s;">check_circle</span>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-3 group-hover:text-gold transition-colors">Tailored Timelines</h3>
                        <p class="text-white/60 text-sm leading-relaxed">Delivery schedules agreed individually for each shipment</p>
                    </div>
                </div>
            </div>

            <!-- Ideal For Section -->
            <div class="pt-16 border-t border-gold/10">
                <div class="inline-flex items-center gap-3 mb-6 justify-center w-full">
                    <span class="h-px w-6 bg-gold"></span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.4em] text-gold">Perfect For</span>
                    <span class="h-px w-6 bg-gold"></span>
                </div>
                <h2 class="serif-text text-4xl md:text-5xl font-bold text-white mb-12 text-center">Ideal For</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-6">
                    <div class="ideal-item group flex flex-col items-center gap-3 p-6 rounded border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] text-center">
                        <span class="material-symbols-outlined text-gold text-3xl mb-2 ideal-icon animate-icon">verified</span>
                        <span class="text-white/85 font-medium text-xs uppercase tracking-widest group-hover:text-white transition-colors">High-value items</span>
                    </div>
                    <div class="ideal-item group flex flex-col items-center gap-3 p-6 rounded border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] text-center">
                        <span class="material-symbols-outlined text-gold text-3xl mb-2 ideal-icon animate-icon" style="animation-delay: 0.1s;">verified</span>
                        <span class="text-white/85 font-medium text-xs uppercase tracking-widest group-hover:text-white transition-colors">Confidential documents</span>
                    </div>
                    <div class="ideal-item group flex flex-col items-center gap-3 p-6 rounded border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] text-center">
                        <span class="material-symbols-outlined text-gold text-3xl mb-2 ideal-icon animate-icon" style="animation-delay: 0.2s;">verified</span>
                        <span class="text-white/85 font-medium text-xs uppercase tracking-widest group-hover:text-white transition-colors">Specialist deliveries</span>
                    </div>
                    <div class="ideal-item group flex flex-col items-center gap-3 p-6 rounded border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] text-center">
                        <span class="material-symbols-outlined text-gold text-3xl mb-2 ideal-icon animate-icon" style="animation-delay: 0.3s;">verified</span>
                        <span class="text-white/85 font-medium text-xs uppercase tracking-widest group-hover:text-white transition-colors">Time-critical</span>
                    </div>
                    <div class="ideal-item group flex flex-col items-center gap-3 p-6 rounded border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] text-center">
                        <span class="material-symbols-outlined text-gold text-3xl mb-2 ideal-icon animate-icon" style="animation-delay: 0.4s;">verified</span>
                        <span class="text-white/85 font-medium text-xs uppercase tracking-widest group-hover:text-white transition-colors">Executive clients</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-32 px-6 md:px-12 bg-mahogany/20 border-y border-white/5">
        <div class="mx-auto max-w-5xl">
            <div class="border border-gold/20 bg-charcoal/50 backdrop-blur-sm p-12 md:p-16 text-center">
                <span class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-6 block">Enquiry Only</span>
                <h2 class="serif-text text-4xl md:text-5xl font-bold text-white mb-8 italic">Bespoke Service</h2>
                
                <div class="mb-8">
                    <p class="text-base md:text-lg font-light text-white/80 leading-relaxed mb-12 max-w-2xl mx-auto">
                        Our VIP White Glove Service is not available for instant booking. Every delivery is reviewed individually to ensure absolute care and security.
                    </p>
                    
                    <div class="grid md:grid-cols-3 gap-8 mt-12 text-left">
                        <div class="flex flex-col items-center text-center gap-4">
                            <span class="material-symbols-outlined text-gold text-3xl animate-icon">check_circle</span>
                            <div>
                                <p class="text-white font-bold mb-2 uppercase tracking-widest text-[11px]">Requirements Reviewed</p>
                                <p class="text-white/60 text-xs">We assess each enquiry on its merits and specific needs</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center text-center gap-4">
                            <span class="material-symbols-outlined text-gold text-3xl animate-icon" style="animation-delay: 0.2s;">check_circle</span>
                            <div>
                                <p class="text-white font-bold mb-2 uppercase tracking-widest text-[11px]">Response by Email</p>
                                <p class="text-white/60 text-xs">Our team will contact you within 24 hours with a detailed proposal</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center text-center gap-4">
                            <span class="material-symbols-outlined text-gold text-3xl animate-icon" style="animation-delay: 0.4s;">check_circle</span>
                            <div>
                                <p class="text-white font-bold mb-2 uppercase tracking-widest text-[11px]">Planned Specifically</p>
                                <p class="text-white/60 text-xs">To client needs – not standardised routes or timelines</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 px-6 md:px-12 bg-charcoal border-t border-white/5">
        <div class="mx-auto max-w-5xl">
            <div class="text-center mb-16">
                <div class="inline-flex items-center gap-3 mb-6">
                    <span class="h-px w-6 bg-gold"></span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.4em] text-gold">Quick Access</span>
                    <span class="h-px w-6 bg-gold"></span>
                </div>
                <h2 class="serif-text text-5xl font-bold text-white mb-4 italic">Submit Your Enquiry</h2>
                <p class="text-white/60 font-light text-base">Tell us about your delivery requirements and our team will respond within 24 hours</p>
            </div>
            
            <!-- VIP Form Shortcode Integration - Ultra-Wide and Sleek -->
            <div class="mx-auto max-w-5xl">
                <?php echo do_shortcode('[vip_courier_form]'); ?>
            </div>
        </div>
    </section>

    <footer class="bg-earth border-t border-white/5 py-24 px-6 md:px-12">
        <div class="mx-auto max-w-7xl">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-16">
                <div class="md:col-span-2">
                    <h2 class="text-lg font-bold tracking-[0.3em] text-white uppercase mb-6">Elite White Glove</h2>
                    <p class="max-w-sm text-xs font-light leading-relaxed text-white/40 uppercase tracking-widest">
                        The definitive standard in global concierge logistics for the world's most discerning individuals and institutions.
                    </p>
                </div>
                <div>
                    <h4 class="text-[11px] font-bold text-gold uppercase tracking-[0.2em] mb-8">Global Hubs</h4>
                    <ul class="space-y-4">
                        <li class="text-[10px] text-white/50 uppercase tracking-widest">London Headquarters</li>
                        <li class="text-[10px] text-white/50 uppercase tracking-widest">New York City, US</li>
                        <li class="text-[10px] text-white/50 uppercase tracking-widest">Geneva, Switzerland</li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-[11px] font-bold text-gold uppercase tracking-[0.2em] mb-8">Navigation</h4>
                    <ul class="space-y-4">
                        <li><a class="text-[10px] text-white/50 hover:text-white uppercase tracking-widest transition-colors" href="#">Mission Brief</a></li>
                        <li><a class="text-[10px] text-white/50 hover:text-white uppercase tracking-widest transition-colors" href="#">Data Security</a></li>
                        <li><a class="text-[10px] text-white/50 hover:text-white uppercase tracking-widest transition-colors" href="#">Legal Notice</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-24 pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                <p class="text-[9px] font-bold text-white/20 uppercase tracking-[0.4em]">©Copyright <a href="https://onroutecouriers.com/" class="text-gold/60 hover:text-gold transition-colors font-bold">OnRoute Couriers</a>. All rights reserved.</p>
                <div class="flex gap-8">
                    <span class="text-[9px] text-gold/40 uppercase tracking-widest border border-gold/20 px-3 py-1">ISO 27001</span>
                    <span class="text-[9px] text-gold/40 uppercase tracking-widest border border-gold/20 px-3 py-1">SIA Registered</span>
                </div>
            </div>
        </div>
    </footer>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
