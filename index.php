<?php
// index.php
require_once 'inc/config.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

// If user is already logged in, go to dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$lang = get_language_strings();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'en'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['app_name']; ?> – Smart Expense & Income Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: #0d631b;
            --primary-fixed: #2e7d32;
            --secondary: #795900;
            --tertiary: #ab1118;
            --surface: #f9f9f9;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f9f9f9 0%, #ffffff 100%);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-fixed));
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(13, 99, 27, 0.3);
        }

        .feature-card {
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="antialiased">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold bg-gradient-to-r from-[var(--primary)] to-[var(--primary-fixed)] bg-clip-text text-transparent"><?php echo $lang['app_name']; ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="login.php" class="text-gray-600 hover:text-[var(--primary)] font-medium transition"><?php echo $lang['login']; ?></a>
                    <a href="register.php" class="btn-primary px-5 py-2 rounded-xl text-white font-semibold shadow-md"><?php echo $lang['register']; ?></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-green-50 via-white to-amber-50 opacity-40"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-gray-900">
                    Take Control of Your
                    <span class="bg-gradient-to-r from-[var(--primary)] to-[var(--primary-fixed)] bg-clip-text text-transparent">Finances</span>
                </h1>
                <p class="mt-6 text-xl text-gray-500 leading-relaxed">
                    Track expenses, set budgets, link with your partner, and get AI‑powered insights – all in one beautiful app.
                </p>
                <div class="mt-10 flex flex-wrap justify-center gap-4">
                    <a href="register.php" class="btn-primary px-8 py-3 rounded-xl text-white font-semibold text-lg shadow-lg">Get Started Free →</a>
                    <a href="#features" class="border border-gray-300 bg-white px-8 py-3 rounded-xl text-gray-700 font-semibold text-lg hover:shadow-md transition">Learn More</a>
                </div>
                <div class="mt-12 flex justify-center gap-8 text-sm text-gray-400">
                    <span>✓ No credit card required</span>
                    <span>✓ Free forever</span>
                    <span>✓ Cancel anytime</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Everything you need to manage money</h2>
                <p class="mt-4 text-xl text-gray-500">Smart features designed for modern couples and individuals</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="feature-card bg-gray-50 rounded-2xl p-6 shadow-sm hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Expense Tracking</h3>
                    <p class="text-gray-500">Log income and expenses in seconds. Categorize, add notes, and view your spending history.</p>
                </div>
                <div class="feature-card bg-gray-50 rounded-2xl p-6 shadow-sm hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-[var(--secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Smart Budgets</h3>
                    <p class="text-gray-500">Set monthly limits per category. Get real‑time progress bars and alerts when you're near the limit.</p>
                </div>
                <div class="feature-card bg-gray-50 rounded-2xl p-6 shadow-sm hover:shadow-xl transition">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-[var(--tertiary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Couple Linking</h3>
                    <p class="text-gray-500">Link with your partner, share finances, schedule surprise dates, and manage kids allowance together.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900">How it works</h2>
                <p class="mt-4 text-xl text-gray-500">Three simple steps to financial clarity</p>
            </div>
            <div class="grid md:grid-cols-3 gap-12">
                <div class="text-center">
                    <div class="w-20 h-20 bg-[var(--primary)] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4 shadow-lg">1</div>
                    <h3 class="text-xl font-bold mb-2">Create Account</h3>
                    <p class="text-gray-500">Sign up in 30 seconds. No credit card required.</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-[var(--primary)] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4 shadow-lg">2</div>
                    <h3 class="text-xl font-bold mb-2">Add Transactions</h3>
                    <p class="text-gray-500">Log your income and expenses. Categorize them for better insights.</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-[var(--primary)] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4 shadow-lg">3</div>
                    <h3 class="text-xl font-bold mb-2">Get Insights</h3>
                    <p class="text-gray-500">View charts, track budgets, and receive AI‑powered recommendations.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonial / Trust -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
            </svg>
            <p class="text-xl text-gray-600 italic">"This app transformed how I manage money."</p>
            <p class="mt-6 font-semibold text-gray-900">— Abebe, Wechiye user</p>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-gradient-to-r from-[var(--primary)] to-[var(--primary-fixed)] text-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl font-bold">Ready to take control of your finances?</h2>
            <p class="mt-4 text-lg text-green-100">Join thousands of users who track smarter.</p>
            <div class="mt-10">
                <a href="register.php" class="inline-block bg-white text-[var(--primary)] px-8 py-3 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition transform hover:-translate-y-1">Start Now – It's Free</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $lang['app_name']; ?>. All rights reserved.</p>
            <p class="mt-2 text-sm">Secure financial tracking for everyone.</p>
        </div>
    </footer>
</body>

</html>