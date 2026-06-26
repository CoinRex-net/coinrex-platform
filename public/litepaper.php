<?php
/**
 * CoinRex Litepaper Page
 * Location: /coinrex/public/litepaper.php
 * Version 2.0 — May 2026
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';
?>
<!-- Litepaper Page Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/litepaper.css">

<main class="litepaper-main">

    <!-- ========================================
         Hero Section
         ======================================== -->
    <section class="litepaper-hero">
        <div class="litepaper-container">
            <div class="litepaper-hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-file-alt"></i>
                    <span>Litepaper</span>
                </div>
                <div class="print-logo">
                    <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="CoinRex Logo">
                </div>
                <h1 class="hero-title animate-fade-up">CoinRex <span class="gradient-text">Litepaper</span></h1>
                <div class="version-badge animate-fade-up delay-1">
                    <i class="fas fa-tag"></i>
                    <span>Version 2.0 — May 2026</span>
                </div>
                <p class="hero-description animate-fade-up delay-1">
                    A crypto review platform where users earn rewards for proof-backed reviews, 
                    and developers build public trust through transparency and community validation.
                </p>
                <div class="hero-actions animate-fade-up delay-2">
                    <a href="javascript:void(0)" onclick="window.print()" class="btn btn-primary" id="downloadPdfBtn">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                    <a href="<?php echo BASE_URL; ?>/auth/auth.php?tab=register" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i> Join CoinRex
                    </a>
                </div>
            </div>
        </div>
        <div class="hero-wave">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#0f172a" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <!-- ========================================
         Main Content
         ======================================== -->
    <div class="litepaper-container">
        <div class="litepaper-content">

            <!-- Inline Table of Contents -->
            <div class="inline-toc">
                <div class="inline-toc-header">
                    <i class="fas fa-list"></i>
                    <span>Table of Contents</span>
                </div>
                <div class="inline-toc-grid">
                    <a href="#introduction" class="inline-toc-item"><span class="toc-num">1</span> Introduction</a>
                    <a href="#problem" class="inline-toc-item"><span class="toc-num">2</span> Problem Statement</a>
                    <a href="#solution" class="inline-toc-item"><span class="toc-num">3</span> The CoinRex Solution</a>
                    <a href="#features" class="inline-toc-item"><span class="toc-num">4</span> Platform Features</a>
                    <a href="#how-it-works" class="inline-toc-item"><span class="toc-num">5</span> How It Works</a>
                    <a href="#developers" class="inline-toc-item"><span class="toc-num">6</span> For Developers</a>
                    <a href="#tokenomics" class="inline-toc-item"><span class="toc-num">7</span> Tokenomics ($REX)</a>
                    <a href="#rewards" class="inline-toc-item"><span class="toc-num">8</span> Reward System</a>
                    <a href="#roadmap" class="inline-toc-item"><span class="toc-num">9</span> Roadmap</a>
                    <a href="#team" class="inline-toc-item"><span class="toc-num">10</span> Team</a>
                    <a href="#community" class="inline-toc-item"><span class="toc-num">11</span> Community</a>
                    <a href="#disclaimer" class="inline-toc-item"><span class="toc-num">12</span> Legal Disclaimer</a>
                </div>
            </div>

                <!-- ========================================
                     Section 1: Introduction
                     ======================================== -->
                <section id="introduction" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-info-circle"></i>
                            <span>Section 1</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Introduction</h2>
                        <p class="section-subtitle animate-fade-up delay-2">What is CoinRex and who is it for?</p>
                    </div>
                    <div class="content-card animate-fade-up delay-3">
                        <p>CoinRex is a <strong>crypto review and trust platform</strong> built for two main audiences:</p>
                        <ul>
                            <li><i class="fas fa-user"></i> <strong>Users / Reviewers</strong> — People who use crypto projects and want to share their experience while earning rewards</li>
                            <li><i class="fas fa-code"></i> <strong>Developers / Project Owners</strong> — Teams building crypto projects who want public feedback and credibility</li>
                        </ul>
                        <p style="margin-top: 16px;">CoinRex exists to make crypto project trust more transparent, more review-driven, and less dependent on hype alone. It combines project discovery, proof-backed reviews, reward earning, and task-based user progression into one unified ecosystem.</p>
                    </div>
                </section>

                <!-- ========================================
                     Section 2: Problem Statement
                     ======================================== -->
                <section id="problem" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Section 2</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Problem Statement</h2>
                        <p class="section-subtitle animate-fade-up delay-2">The challenges facing the crypto ecosystem today</p>
                    </div>
                    <div class="problem-grid">
                        <div class="problem-card animate-fade-up delay-3">
                            <div class="problem-icon"><i class="fas fa-skull"></i></div>
                            <h4>Scams vs Real Projects</h4>
                            <p>Crypto users don't know which projects are real and which are scams. There's no reliable way to verify legitimacy.</p>
                        </div>
                        <div class="problem-card animate-fade-up delay-4">
                            <div class="problem-icon"><i class="fas fa-pen-fancy"></i></div>
                            <h4>Fake & Paid Reviews</h4>
                            <p>Reviews are often fake, paid, or unverifiable. Users cannot trust what they read on most platforms.</p>
                        </div>
                        <div class="problem-card animate-fade-up delay-5">
                            <div class="problem-icon"><i class="fas fa-coins"></i></div>
                            <h4>No Rewards for Honesty</h4>
                            <p>No platform rewards honest reviewers for their valuable contributions to the community.</p>
                        </div>
                        <div class="problem-card animate-fade-up delay-3">
                            <div class="problem-icon"><i class="fas fa-comments"></i></div>
                            <h4>No Centralized Feedback Hub</h4>
                            <p>Developers lack a centralized place to receive genuine public feedback about their projects.</p>
                        </div>
                        <div class="problem-card animate-fade-up delay-4">
                            <div class="problem-icon"><i class="fas fa-chart-line"></i></div>
                            <h4>Marketing-Driven Trust</h4>
                            <p>Trust signals are marketing-driven, not community-driven. Real user experiences are hard to find.</p>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     Section 3: The CoinRex Solution
                     ======================================== -->
                <section id="solution" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-lightbulb"></i>
                            <span>Section 3</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">The CoinRex Solution</h2>
                        <p class="section-subtitle animate-fade-up delay-2">How we solve the trust problem in crypto</p>
                    </div>
                    <div class="solution-grid">
                        <div class="solution-card animate-fade-up delay-3">
                            <div class="solution-icon"><i class="fas fa-shield-alt"></i></div>
                            <h4>Proof-Backed Reviews</h4>
                            <p>Users submit reviews backed by proof — transaction hashes, wallet balances, and screenshots — making every review verifiable.</p>
                        </div>
                        <div class="solution-card animate-fade-up delay-4">
                            <div class="solution-icon"><i class="fas fa-check-double"></i></div>
                            <h4>Quality Moderation</h4>
                            <p>All reviews go through moderation to ensure quality, authenticity, and relevance before being published.</p>
                        </div>
                        <div class="solution-card animate-fade-up delay-5">
                            <div class="solution-icon"><i class="fas fa-gift"></i></div>
                            <h4>$REX Rewards</h4>
                            <p>Users earn $REX rewards for valuable contributions — writing reviews, completing tasks, and referring others.</p>
                        </div>
                        <div class="solution-card animate-fade-up delay-3">
                            <div class="solution-icon"><i class="fas fa-building"></i></div>
                            <h4>Developer Listings</h4>
                            <p>Developers can list their projects and receive visible, public feedback from real users.</p>
                        </div>
                        <div class="solution-card animate-fade-up delay-4">
                            <div class="solution-icon"><i class="fas fa-eye"></i></div>
                            <h4>Transparent Trust</h4>
                            <p>Trust becomes transparent and verifiable through public review activity, proof, and community participation.</p>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     Section 4: Platform Features
                     ======================================== -->
                <section id="features" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-star"></i>
                            <span>Section 4</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Platform Features</h2>
                        <p class="section-subtitle animate-fade-up delay-2">All features are <strong>FREE for users</strong></p>
                    </div>
                    <div class="content-card animate-fade-up delay-3" style="padding: 0; overflow: hidden;">
                        <table class="feature-table">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th>Description</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Registration</td>
                                    <td>Create an account and join the platform</td>
                                    <td><span class="free-badge"><i class="fas fa-check"></i> Free Forever</span></td>
                                </tr>
                                <tr>
                                    <td>LearnHub</td>
                                    <td>10-day mission system to learn and earn rewards</td>
                                    <td><span class="free-badge"><i class="fas fa-check"></i> Free</span></td>
                                </tr>
                                <tr>
                                    <td>BoostHub</td>
                                    <td>Micro-tasks for additional rewards</td>
                                    <td><span class="free-badge"><i class="fas fa-check"></i> Free</span></td>
                                </tr>
                                <tr>
                                    <td>Reviews</td>
                                    <td>Unlimited proof-backed review submissions</td>
                                    <td><span class="free-badge"><i class="fas fa-check"></i> Free</span></td>
                                </tr>
                                <tr>
                                    <td>$REX Earning</td>
                                    <td>Earn rewards for completing tasks and reviews</td>
                                    <td><span class="free-badge"><i class="fas fa-check"></i> Free</span></td>
                                </tr>
                                <tr>
                                    <td>$REX Claim</td>
                                    <td>First 5,000 beta testers claim free; later users pay the network fee</td>
                                    <td><span class="fee-badge"><i class="fas fa-gas-pump"></i> 1 POL (~$0.06)</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ========================================
                     Section 5: How It Works (User Flow)
                     ======================================== -->
                <section id="how-it-works" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-arrows-alt"></i>
                            <span>Section 5</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">How It Works</h2>
                        <p class="section-subtitle animate-fade-up delay-2">The user journey from registration to earning rewards</p>
                    </div>
                    <div class="steps-grid">
                        <div class="step-card animate-fade-up delay-3">
                            <div class="step-number">1</div>
                            <h4><i class="fas fa-user-plus"></i> User Joins</h4>
                            <p>Register on CoinRex for free. No hidden fees, no credit card required. Just create your account and start exploring.</p>
                        </div>
                        <div class="step-card animate-fade-up delay-4">
                            <div class="step-number">2</div>
                            <h4><i class="fas fa-list-check"></i> Completes LearnHub</h4>
                            <p>Go through the 10-day LearnHub mission system. Each day introduces new tasks that teach you how the platform works while earning rewards.</p>
                        </div>
                        <div class="step-card animate-fade-up delay-5">
                            <div class="step-number">3</div>
                            <h4><i class="fas fa-pen-alt"></i> Submits Reviews</h4>
                            <p>Write proof-backed reviews for crypto projects. Attach transaction hashes, screenshots, or wallet evidence to make your review credible.</p>
                        </div>
                        <div class="step-card animate-fade-up delay-3">
                            <div class="step-number">4</div>
                            <h4><i class="fas fa-coins"></i> Earns $REX</h4>
                            <p>Earn $REX rewards for every approved review, completed task, and successful referral. The more you contribute, the more you earn.</p>
                        </div>
                        <div class="step-card animate-fade-up delay-4">
                            <div class="step-number">5</div>
                            <h4><i class="fas fa-hand-holding-usd"></i> Claims Rewards</h4>
                            <p>Claim your accumulated $REX rewards to your wallet. The first 5,000 beta testers claim free; later users pay a small network fee of 1 POL (~$0.06) to cover Polygon gas costs.</p>
                        </div>
                        <div class="step-card animate-fade-up delay-5">
                            <div class="step-number">6</div>
                            <h4><i class="fas fa-crown"></i> Builds Trust Score</h4>
                            <p>Progress from Beginner → Pro → Expert as you contribute more. Higher levels unlock greater rewards, credibility, and platform benefits.</p>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     Section 6: For Developers & Businesses
                     ======================================== -->
                <section id="developers" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-building"></i>
                            <span>Section 6</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">For Developers & Businesses</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Everything is <strong>FREE</strong> in the early phase — no pricing until 10K active users</p>
                    </div>
                    <div class="content-card animate-fade-up delay-3">
                        <h3><i class="fas fa-info-circle"></i> Current Status: Early Access — All Free</h3>
                        <p>CoinRex is currently in its <strong>early growth phase</strong>. We are not charging anything for any feature until we reach <strong>10K active users</strong>. Everything listed below is available to developers at no cost.</p>
                    </div>
                    <div class="dev-benefits-grid">
                        <div class="dev-benefit-card animate-fade-up delay-3">
                            <div class="dev-benefit-icon"><i class="fas fa-list"></i></div>
                            <h4>Project Listing</h4>
                            <p>Submit your project for listing and receive reviews from real users. Build credibility through community feedback. <strong>Free</strong></p>
                        </div>
                        <div class="dev-benefit-card animate-fade-up delay-4">
                            <div class="dev-benefit-icon"><i class="fas fa-star"></i></div>
                            <h4>Public Reviews & Trust</h4>
                            <p>Collect genuine public feedback from real users. Use reviews as trust signals to show your project is active and community-validated. <strong>Free</strong></p>
                        </div>
                        <div class="dev-benefit-card animate-fade-up delay-5">
                            <div class="dev-benefit-icon"><i class="fas fa-code"></i></div>
                            <h4>DevHub Access</h4>
                            <p>Apply for developer verification, manage your project presence, and access the developer dashboard. <strong>Free</strong></p>
                        </div>
                        <div class="dev-benefit-card animate-fade-up delay-3">
                            <div class="dev-benefit-icon"><i class="fas fa-badge-check"></i></div>
                            <h4>Verified Developer Badge</h4>
                            <p>Get verified through the DevHub application process and earn the verified badge — a trust signal for your project. <strong>Free</strong></p>
                        </div>
                        <div class="dev-benefit-card animate-fade-up delay-4">
                            <div class="dev-benefit-icon"><i class="fas fa-rocket"></i></div>
                            <h4>Sponsored Visibility</h4>
                            <p>Apply for sponsored project placement through token-gated applications (admin-provided tokens). Visibility without compromising trust. <strong>Token-gated</strong></p>
                        </div>
                    </div>
                    <div class="pricing-note animate-fade-up delay-5">
                        <i class="fas fa-check-circle"></i>
                        <span>Platform Revenue Model: Future monetization will focus on visibility services for businesses. Users always remain free. No user data is ever sold. Pricing will be announced after reaching 10K active users.</span>
                    </div>
                </section>

                <!-- ========================================
                     Section 7: Tokenomics ($REX)
                     ======================================== -->
                <section id="tokenomics" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-coins"></i>
                            <span>Section 7</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Tokenomics ($REX)</h2>
                        <p class="section-subtitle animate-fade-up delay-2">The economic model powering the CoinRex ecosystem</p>
                    </div>

                    <!-- Token Summary -->
                    <div class="content-card animate-fade-up delay-3">
                        <h3><i class="fas fa-info-circle"></i> Token Overview</h3>
                        <ul>
                            <li><i class="fas fa-tag"></i> <strong>Token Symbol:</strong> $REX</li>
                            <li><i class="fas fa-cubes"></i> <strong>Total Supply:</strong> 1,000,000,000 (1 Billion)</li>
                            <li><i class="fas fa-link"></i> <strong>Network:</strong> Polygon (initially), Plasma (planned)</li>
                        </ul>
                    </div>

                    <!-- Allocation Table + Chart -->
                    <div class="tokenomics-grid">
                        <div class="tokenomics-table-wrap animate-fade-up delay-4">
                            <table class="tokenomics-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Percentage</th>
                                        <th>Amount</th>
                                        <th>Vesting</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Locked (Treasury/Team)</td>
                                        <td class="token-pct">40%</td>
                                        <td>400M</td>
                                        <td>12-month cliff, 36-month vesting</td>
                                    </tr>
                                    <tr>
                                        <td>Rewards (LearnHub/BoostHub/Reviews)</td>
                                        <td class="token-pct">20%</td>
                                        <td>200M</td>
                                        <td>5-year emission schedule</td>
                                    </tr>
                                    <tr>
                                        <td>Liquidity Pool</td>
                                        <td class="token-pct">12%</td>
                                        <td>120M</td>
                                        <td>Unlocked at TGE</td>
                                    </tr>
                                    <tr>
                                        <td>Presale</td>
                                        <td class="token-pct">12%</td>
                                        <td>120M</td>
                                        <td>3-month cliff, 12-month vesting</td>
                                    </tr>
                                    <tr>
                                        <td>Early User + Referral Airdrop</td>
                                        <td class="token-pct">8%</td>
                                        <td>80M</td>
                                        <td>50% at TGE, 50% after 6 months</td>
                                    </tr>
                                    <tr>
                                        <td>Ecosystem Fund (Operations)</td>
                                        <td class="token-pct">8%</td>
                                        <td>80M</td>
                                        <td>6-month cliff, 24-month vesting</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Visual Bar Chart -->
                        <div class="tokenomics-chart animate-fade-up delay-5">
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Locked (Treasury/Team)</span>
                                    <span>40%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill locked" style="width: 40%;"><span class="bar-pct">40%</span></div>
                                </div>
                            </div>
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Rewards (LearnHub/BoostHub/Reviews)</span>
                                    <span>20%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill rewards" style="width: 20%;"><span class="bar-pct">20%</span></div>
                                </div>
                            </div>
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Liquidity Pool</span>
                                    <span>12%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill liquidity" style="width: 12%;"><span class="bar-pct">12%</span></div>
                                </div>
                            </div>
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Presale</span>
                                    <span>12%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill presale" style="width: 12%;"><span class="bar-pct">12%</span></div>
                                </div>
                            </div>
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Early User + Referral Airdrop</span>
                                    <span>8%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill airdrop" style="width: 8%;"><span class="bar-pct">8%</span></div>
                                </div>
                            </div>
                            <div class="token-bar-item">
                                <div class="token-bar-label">
                                    <span>Ecosystem Fund (Operations)</span>
                                    <span>8%</span>
                                </div>
                                <div class="token-bar-track">
                                    <div class="token-bar-fill ecosystem" style="width: 8%;"><span class="bar-pct">8%</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vesting Summary -->
                    <div class="section-header" style="margin-top: 32px;">
                        <h3 class="section-title" style="font-size: 22px;">Vesting Summary</h3>
                    </div>
                    <div class="vesting-grid">
                        <div class="vesting-card animate-fade-up delay-3">
                            <h5><i class="fas fa-lock"></i> Team Tokens</h5>
                            <p>Locked 12 months, then 36-month linear vesting. Ensures long-term commitment from the team.</p>
                        </div>
                        <div class="vesting-card animate-fade-up delay-4">
                            <h5><i class="fas fa-clock"></i> Presale Tokens</h5>
                            <p>3-month cliff, then 12-month linear vesting. Protects early investors and prevents dumps.</p>
                        </div>
                        <div class="vesting-card animate-fade-up delay-5">
                            <h5><i class="fas fa-seedling"></i> Ecosystem Fund</h5>
                            <p>6-month cliff, then 24-month linear vesting. Funds platform growth and operations sustainably.</p>
                        </div>
                        <div class="vesting-card animate-fade-up delay-3">
                            <h5><i class="fas fa-gift"></i> Airdrop</h5>
                            <p>50% at TGE, 50% after 6 months. Rewards early adopters while maintaining token stability.</p>
                        </div>
                        <div class="vesting-card animate-fade-up delay-4">
                            <h5><i class="fas fa-water"></i> Liquidity Pool</h5>
                            <p>100% unlocked at TGE. Ensures immediate liquidity for trading on decentralized exchanges.</p>
                        </div>
                    </div>

                    <!-- Presale Details -->
                    <div class="presale-card animate-fade-up delay-5">
                        <h4><i class="fas fa-gavel"></i> Presale Details</h4>
                        <div class="presale-details">
                            <div class="presale-item">
                                <span class="presale-label">Price</span>
                                <span class="presale-value">$0.01</span>
                            </div>
                            <div class="presale-item">
                                <span class="presale-label">Target Raise</span>
                                <span class="presale-value">$1,000,000</span>
                            </div>
                            <div class="presale-item">
                                <span class="presale-label">Allocation</span>
                                <span class="presale-value">12% (120M REX)</span>
                            </div>
                            <div class="presale-item">
                                <span class="presale-label">FDV at Presale</span>
                                <span class="presale-value">$10,000,000</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     Section 8: Reward System
                     ======================================== -->
                <section id="rewards" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-gift"></i>
                            <span>Section 8</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Reward System</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Multiple ways to earn $REX on CoinRex</p>
                    </div>
                    <div class="reward-grid">
                        <div class="reward-card animate-fade-up delay-3">
                            <div class="reward-icon"><i class="fas fa-list-check"></i></div>
                            <h4>LearnHub Missions</h4>
                            <p>Complete the 10-day mission system to earn $REX. Each day introduces new tasks that build your understanding of the platform.</p>
                        </div>
                        <div class="reward-card animate-fade-up delay-4">
                            <div class="reward-icon"><i class="fas fa-bolt"></i></div>
                            <h4>BoostHub Micro-Tasks</h4>
                            <p>Complete micro-tasks through BoostHub for additional $REX rewards. Quick tasks that add up over time.</p>
                        </div>
                        <div class="reward-card animate-fade-up delay-5">
                            <div class="reward-icon"><i class="fas fa-pen-alt"></i></div>
                            <h4>Proof-Backed Reviews</h4>
                            <p>Submit reviews with proof (transaction hashes, screenshots) and earn $REX for every approved review.</p>
                        </div>
                        <div class="reward-card animate-fade-up delay-3">
                            <div class="reward-icon"><i class="fas fa-users"></i></div>
                            <h4>Referral Program</h4>
                            <p>Earn 10% commission on rewards earned by users you refer. Build your network and grow your earnings passively.</p>
                        </div>
                        <div class="reward-card animate-fade-up delay-4">
                            <div class="reward-icon"><i class="fas fa-rocket"></i></div>
                            <h4>Early Access Airdrop</h4>
                            <p>The first 100,000 users receive bonus $REX from the 8% of total supply allocated for the early adopter airdrop.</p>
                        </div>
                    </div>

                    <!-- Claim Fee Info -->
                    <div class="content-card animate-fade-up delay-5" style="margin-top: 20px;">
                        <h3><i class="fas fa-gas-pump"></i> Claim Fee</h3>
                        <p>The first <strong>5,000 beta testers</strong> can claim their $REX rewards for free. After this beta tester allocation is filled, a small network fee of <strong>1 POL (~$0.06)</strong> is charged to cover Polygon gas costs. This is the only cost users ever pay on CoinRex.</p>
                    </div>
                </section>

                <!-- ========================================
                     Section 9: Roadmap (Placeholder)
                     ======================================== -->
                <section id="roadmap" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-map"></i>
                            <span>Section 9</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Roadmap</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Our journey ahead</p>
                    </div>
                    <div class="roadmap-placeholder animate-fade-up delay-3">
                        <div class="rp-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <h3>🚀 Roadmap — Coming Soon</h3>
                        <p>We're working on a detailed roadmap that outlines our vision for the future of CoinRex. Stay tuned for updates as we continue to build and grow the platform.</p>
                        <a href="<?php echo BASE_URL; ?>/public/roadmap.php" class="btn-outline">
                            <i class="fas fa-arrow-right"></i> View Full Roadmap
                        </a>
                    </div>
                </section>

                <!-- ========================================
                     Section 10: Team
                     ======================================== -->
                <section id="team" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-users"></i>
                            <span>Section 10</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Team</h2>
                        <p class="section-subtitle animate-fade-up delay-2">The people behind CoinRex</p>
                    </div>
                    <div class="team-card animate-fade-up delay-3">
                        <div class="team-avatar">
                            <img src="<?php echo BASE_URL; ?>/uploads/extras/team.png" alt="Muhammad Irfan" class="team-avatar-img">
                        </div>
                        <h3>Muhammad Irfan</h3>
                        <div class="team-role">Founder & Lead Developer</div>
                        <p>Muhammad Irfan is the founder and lead developer of CoinRex. As a full-stack developer and blockchain enthusiast, he is responsible for the platform's architecture, development, and long-term vision.</p>
                        <p>From concept to implementation, CoinRex has been built under his leadership, covering backend systems, frontend development, security design, developer tools, and the RexLink ecosystem.</p>
                        <p>Driven by a mission to improve trust and transparency in Web3, he is focused on building practical products that empower both users and developers.</p>
                    </div>
                </section>

                <!-- ========================================
                     Section 11: Community & Channels
                     ======================================== -->
                <section id="community" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-globe"></i>
                            <span>Section 11</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Community & Channels</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Join the CoinRex community</p>
                    </div>
                    <div class="community-grid">
                        <a href="https://t.me/CoinRex" target="_blank" rel="noopener noreferrer" class="community-card animate-fade-up delay-3">
                            <div class="community-icon telegram">
                                <i class="fab fa-telegram-plane"></i>
                            </div>
                            <h4>Telegram</h4>
                            <span class="community-handle">@CoinRex</span>
                        </a>
                        <a href="https://x.com/CoinRex" target="_blank" rel="noopener noreferrer" class="community-card animate-fade-up delay-4">
                            <div class="community-icon twitter">
                                <i class="fab fa-twitter"></i>
                            </div>
                            <h4>X (Twitter)</h4>
                            <span class="community-handle">@CoinRex</span>
                        </a>
                        <a href="https://discord.gg/CoinRex" target="_blank" rel="noopener noreferrer" class="community-card animate-fade-up delay-5">
                            <div class="community-icon discord">
                                <i class="fab fa-discord"></i>
                            </div>
                            <h4>Discord</h4>
                            <span class="community-handle">CoinRex Community</span>
                        </a>
                    </div>
                </section>

                <!-- ========================================
                     Section 12: Legal Disclaimer
                     ======================================== -->
                <section id="disclaimer" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-scale-balanced"></i>
                            <span>Section 12</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Legal Disclaimer</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Important information for all readers</p>
                    </div>
                    <div class="disclaimer-card animate-fade-up delay-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <p><strong>Not Financial Advice:</strong> This litepaper is for informational purposes only and does not constitute financial advice, investment advice, or a recommendation to purchase any tokens or participate in any offering. Cryptocurrency investments carry significant risk and may result in total loss of capital.</p>
                            <p style="margin-top: 12px;"><strong>No Guarantees:</strong> While we strive for accuracy, CoinRex makes no guarantees regarding the completeness, reliability, or accuracy of the information presented. All aspects of the platform, tokenomics, and roadmap are subject to change based on market conditions, regulatory requirements, and strategic decisions.</p>
                            <p style="margin-top: 12px;"><strong>Regulatory Compliance:</strong> Users and participants are responsible for ensuring compliance with their local laws and regulations regarding cryptocurrency participation. CoinRex does not accept liability for any losses or damages arising from the use of this platform or the information provided herein.</p>
                            <p style="margin-top: 12px;"><strong>Forward-Looking Statements:</strong> This document contains forward-looking statements about future plans, developments, and expectations. These statements are based on current assumptions and are subject to risks and uncertainties that could cause actual results to differ materially.</p>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     FAQ Section
                     ======================================== -->
                <section id="faq" class="litepaper-section">
                    <div class="section-header">
                        <div class="section-kicker animate-fade-up">
                            <i class="fas fa-question-circle"></i>
                            <span>FAQ</span>
                        </div>
                        <h2 class="section-title animate-fade-up delay-1">Frequently Asked Questions</h2>
                        <p class="section-subtitle animate-fade-up delay-2">Common questions about CoinRex</p>
                    </div>
                    <div class="faq-list">
                        <div class="faq-item animate-fade-up delay-3">
                            <div class="faq-question-static">
                                <i class="fas fa-question-circle"></i>
                                <span>Is CoinRex free to use?</span>
                            </div>
                            <div class="faq-answer-static">
                                <p>Yes! CoinRex is completely free for all users. Registration, LearnHub missions, BoostHub micro-tasks, and review submissions are all free. The first 5,000 beta testers can claim $REX rewards for free; after that, the only cost is a small network fee of 1 POL (~$0.06) when claiming rewards to your wallet.</p>
                            </div>
                        </div>
                        <div class="faq-item animate-fade-up delay-4">
                            <div class="faq-question-static">
                                <i class="fas fa-question-circle"></i>
                                <span>How do I earn $REX tokens?</span>
                            </div>
                            <div class="faq-answer-static">
                                <p>You can earn $REX by completing LearnHub missions, participating in BoostHub micro-tasks, submitting proof-backed reviews, referring new users (10% commission), and being part of the early access airdrop for the first 100,000 users.</p>
                            </div>
                        </div>
                        <div class="faq-item animate-fade-up delay-5">
                            <div class="faq-question-static">
                                <i class="fas fa-question-circle"></i>
                                <span>What makes CoinRex reviews trustworthy?</span>
                            </div>
                            <div class="faq-answer-static">
                                <p>All reviews on CoinRex are proof-backed. Users must attach evidence such as transaction hashes, wallet balances, or screenshots to verify their claims. Every review goes through moderation to ensure quality and authenticity before being published.</p>
                            </div>
                        </div>
                        <div class="faq-item animate-fade-up delay-3">
                            <div class="faq-question-static">
                                <i class="fas fa-question-circle"></i>
                                <span>When will $REX be tradable?</span>
                            </div>
                            <div class="faq-answer-static">
                                <p>$REX will become tradable after the Token Generation Event (TGE), which is planned after the presale phase. Initially, $REX will be listed on QuickSwap (DEX), followed by centralized exchanges (CEX) as the platform grows.</p>
                            </div>
                        </div>
                        <div class="faq-item animate-fade-up delay-4">
                            <div class="faq-question-static">
                                <i class="fas fa-question-circle"></i>
                                <span>How do developers benefit from CoinRex?</span>
                            </div>
                            <div class="faq-answer-static">
                                <p>Developers can list their projects, receive genuine public feedback, get verified through DevHub, apply for sponsored visibility, and build trust with the community. All developer features are currently free during the early growth phase.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================
                     Final CTA Section
                     ======================================== -->
                <section class="litepaper-cta">
                    <div class="litepaper-cta-card animate-fade-up delay-3">
                        <h2>Ready to Join CoinRex?</h2>
                        <p>Start earning $REX rewards today. Register for free and become part of the most transparent crypto review platform.</p>
                        <div class="litepaper-cta-actions">
                            <a href="<?php echo BASE_URL; ?>/auth/auth.php?tab=register" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Create Free Account
                            </a>
                            <a href="javascript:void(0)" onclick="window.print()" class="btn btn-outline">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </section>

            </div><!-- /.litepaper-content -->
        </div><!-- /.litepaper-container -->
</main>

<!-- No JavaScript needed — static layout, no accordion, no scroll tracking -->


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
