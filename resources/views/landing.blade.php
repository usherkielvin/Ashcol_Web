<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ashcol Airconditioning Corporation - Professional AC Services</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('ashcol/styles.css') }}">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="{{ asset('ashcol/ash.JPG') }}" alt="Ashcol Logo" class="logo-img">
                <span class="logo-text">Ashcol Airconditioning</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="#home" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="#services" class="nav-link">Services</a></li>
                <li class="nav-item"><a href="#about" class="nav-link">About</a></li>
                <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
                <li class="nav-item"><a href="{{ route('tickets.create') }}" class="nav-link">Request Service</a></li>
                @auth
                    <li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a></li>
                @else
                    <li class="nav-item"><a href="{{ route('login') }}" class="nav-link">Login</a></li>
                @endauth
            </ul>
            <div class="hamburger"><span class="bar"></span><span class="bar"></span><span class="bar"></span></div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Professional Air Conditioning Services</h1>
                <p>Your trusted partner for all air conditioning needs. Professional installation, repair, maintenance, and system upgrades for residential and commercial properties.</p>
                <div class="hero-buttons">
                    <a href="{{ route('tickets.create') }}" class="btn btn-primary">Request Service</a>
                    <a href="#services" class="btn btn-secondary">Our Services</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-card">
                    <i class="fas fa-headset"></i>
                    <h3>Customer Care</h3>
                    <p>Get in touch with our expert team</p>
                    <div class="contact-options">
                        <a href="tel:+639778509986" class="contact-option"><i class="fas fa-phone"></i><span>+63 977 850 9986</span></a>
                        <a href="mailto:headoffice@ashcol.com.ph" class="contact-option"><i class="fas fa-envelope"></i><span>headoffice@ashcol.com.ph</span></a>
                    </div>
                    <a href="https://www.facebook.com/AshcolCorp" class="inquire-btn" target="_blank" rel="noopener">Inquire Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services -->
    <section id="services" class="services">
        <div class="container">
            <div class="section-header">
                <h2>Our Services</h2>
                <p>Comprehensive air conditioning solutions for residential and commercial properties</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-tools"></i></div>
                    <h3>Installation</h3>
                    <p>Professional installation of new air conditioning systems for homes and businesses.</p>
                    <ul>
                        <li>Residential AC Units</li>
                        <li>Commercial Systems</li>
                        <li>Ductless Mini-Splits</li>
                        <li>Heat Pumps</li>
                    </ul>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-wrench"></i></div>
                    <h3>Repair & Maintenance</h3>
                    <p>Expert repair services and preventive maintenance to keep your system running efficiently.</p>
                    <ul>
                        <li>Emergency Repairs</li>
                        <li>Preventive Maintenance</li>
                        <li>System Diagnostics</li>
                        <li>Performance Optimization</li>
                    </ul>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-cog"></i></div>
                    <h3>System Upgrades</h3>
                    <p>Upgrade your existing system for better efficiency, comfort, and energy savings.</p>
                    <ul>
                        <li>Energy-Efficient Upgrades</li>
                        <li>Smart Thermostats</li>
                        <li>Air Quality Improvements</li>
                        <li>Zoning Systems</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- About -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-image">
                    <img src="{{ asset('ashcol/red.jpg') }}" alt="Ashcol Team" style="width:100%;max-width:600px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);margin-bottom:1.5rem;">
                </div>
                <div class="about-text" style="text-align: justify;">
                    <h2 style="color:#39B54A;font-size:2.5rem;margin-bottom:1.5rem;">ABOUT US</h2>
                    <p><b>ASHCOL Airconditioning Services Corporation</b> is a family run business, located in Units 4D, 4E and 4F Genesis Building 182-A 19th Avenue Brgy East Rembo Makati City 1216. It was founded in 2014 and the company name ASHCOL has been formed from the names of the two lovely daughters Ashanti and Coleene.</p>
                    <p>They gather their best technical team to ensure that together they will combine sales and marketing plus customer service and technical skills to provide a relation based customer service for a win-win situation for both parties.</p>
                    <p>The couple came up with this tag line "Keeping YOU Cool" because they believe that if you give the right comfort that people deserve, then hopefully you will never have an unhappy customer".</p>
                    <a href="#contact" class="btn btn-primary" style="margin-top:1.5rem;">READ MORE</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="section-header">
                <h2>Contact Us</h2>
                <p>Get in touch for a free consultation or to discuss your service needs</p>
            </div>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item"><i class="fas fa-phone"></i><div><h3>Customer Hotline</h3><p><a href="tel:+63277291293">+63 (2) 77291293</a></p></div></div>
                    <div class="contact-item"><i class="fas fa-tools"></i><div><h3>Service / Technical Department</h3><p><a href="tel:+63277541418">+63 (2) 77541418</a></p></div></div>
                    <div class="contact-item"><i class="fas fa-mobile-alt"></i><div><h3>Mobile Numbers</h3><p><a href="tel:+639177143810">+63 (917) 7143810</a> (Globe)</p><p><a href="tel:+639088950849">+63 (908) 8950849</a> (Smart)</p></div></div>
                    <div class="contact-item"><i class="fas fa-envelope"></i><div><h3>Email</h3><p><a href="mailto:headoffice@ashcol.com.ph">headoffice@ashcol.com.ph</a></p></div></div>
                </div>
                <div class="contact-form">
                    @if(session('status'))
                        <div style="background:#d1fae5;color:#065f46;padding:10px;border-radius:8px;margin-bottom:10px;">
                            {{ session('status') }}
                        </div>
                    @endif
                    <form id="contactForm" method="POST" action="{{ route('contact.store') }}">
                        @csrf
                        <div class="form-group">
                            <input type="text" id="name" name="name" placeholder="Your Name" value="{{ old('name') }}" required>
                            @error('name')
                                <div style="color:#b91c1c;font-size:0.9rem;margin-top:6px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <input type="email" id="email" name="email" placeholder="Your Email" value="{{ old('email') }}" required>
                            @error('email')
                                <div style="color:#b91c1c;font-size:0.9rem;margin-top:6px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <input type="tel" id="phone" name="phone" placeholder="Your Phone" value="{{ old('phone') }}" required>
                            @error('phone')
                                <div style="color:#b91c1c;font-size:0.9rem;margin-top:6px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group"><select id="service" name="service" required>
                            <option value="">Select Service</option>
                            <option value="installation" {{ old('service')=='installation' ? 'selected' : '' }}>Installation</option>
                            <option value="repair" {{ old('service')=='repair' ? 'selected' : '' }}>Repair</option>
                            <option value="maintenance" {{ old('service')=='maintenance' ? 'selected' : '' }}>Maintenance</option>
                            <option value="consultation" {{ old('service')=='consultation' ? 'selected' : '' }}>Consultation</option>
                            <option value="other" {{ old('service')=='other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('service')
                            <div style="color:#b91c1c;font-size:0.9rem;margin-top:6px;">{{ $message }}</div>
                        @enderror
                        </div>
                        <div class="form-group">
                            <textarea id="message" name="message" placeholder="Describe your needs..." rows="5" required>{{ old('message') }}</textarea>
                            @error('message')
                                <div style="color:#b91c1c;font-size:0.9rem;margin-top:6px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="{{ asset('ashcol/ash.JPG') }}" alt="Ashcol Logo" class="logo-img">
                        <span class="logo-text">Ashcol Airconditioning</span>
                    </div>
                    <p>Your trusted partner for all air conditioning needs. Professional, reliable, and affordable services.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/AshcolCorp" class="facebook" target="_blank" rel="noopener"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="linkedin"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                <div class="footer-section"><h3>Services</h3><ul>
                    <li><a href="#services">Installation</a></li>
                    <li><a href="#services">Repair & Maintenance</a></li>
                    <li><a href="#services">System Upgrades</a></li>
                </ul></div>
                <div class="footer-section"><h3>Quick Links</h3><ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="#services">Services</a></li>
                </ul></div>
                <div class="footer-section"><h3>Contact Info</h3>
                    <p><i class="fas fa-phone"></i> <a href="tel:+63277291293">+63 (2) 77291293</a></p>
                    <p><i class="fas fa-tools"></i> <a href="tel:+63277541418">+63 (2) 77541418</a></p>
                    <p><i class="fas fa-mobile-alt"></i> <a href="tel:+639177143810">+63 (917) 7143810</a></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:headoffice@ashcol.com.ph">headoffice@ashcol.com.ph</a></p>
                </div>
            </div>
            <div class="footer-bottom"><p>&copy; {{ date('Y') }} Ashcol Airconditioning Corporation. All rights reserved.</p></div>
        </div>
    </footer>

    <script src="{{ asset('ashcol/script.js') }}"></script>
</body>
</html>
