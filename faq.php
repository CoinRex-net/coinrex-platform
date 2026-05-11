<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';

$faq_sections = [
    [
        'slug' => 'general',
        'icon' => 'fa-circle-info',
        'title' => 'General Questions',
        'description' => 'Start here for the big-picture view of what CoinRex is and how it works.',
        'items' => [
            [
                'question' => 'What is CoinRex?',
                'answer' => 'CoinRex is a moderated crypto platform where users can discover verified projects, submit proof-based reviews, and earn rewards. Unlike traditional listing sites, CoinRex focuses on trust by ensuring that all content is reviewed before becoming public.',
            ],
            [
                'question' => 'How does CoinRex work?',
                'answer' => 'CoinRex operates on a three-layer system: users submit reviews with proof, developers submit and verify projects, and admins review and approve all content before it becomes visible.',
            ],
            [
                'question' => 'Is CoinRex free to use?',
                'answer' => 'Yes, CoinRex is completely free for users to browse projects, create accounts, and submit reviews.',
            ],
            [
                'question' => 'What makes CoinRex different from other platforms?',
                'answer' => 'CoinRex uses a moderation-first approach. Projects and reviews are only published after verification, making it a trust-driven platform instead of a simple listing directory.',
            ],
            [
                'question' => 'Is CoinRex an exchange or investment platform?',
                'answer' => 'No. CoinRex does not provide trading or investment services. It is purely a discovery and review platform.',
            ],
        ],
    ],
    [
        'slug' => 'reviewers',
        'icon' => 'fa-user-pen',
        'title' => 'Reviewer and User FAQs',
        'description' => 'Everything regular users need to know about accounts, proof, reviews, rewards, and growth.',
        'items' => [
            [
                'question' => 'How do I create an account?',
                'answer' => 'You can register using the signup form. After registration, you must verify your email via OTP before accessing full features.',
            ],
            [
                'question' => 'Why do I need to verify my email?',
                'answer' => 'Email verification ensures account authenticity and prevents spam or fake activity on the platform.',
            ],
            [
                'question' => 'How can I submit a review?',
                'answer' => 'Browse a project, open its detail page, and click "Submit Review." Follow the multi-step process including proof submission and scoring.',
            ],
            [
                'question' => 'What is a proof-backed review?',
                'answer' => 'A proof-backed review is a review supported by evidence such as screenshots, transaction logs, or interaction records that validate your experience.',
            ],
            [
                'question' => 'What type of proof is required?',
                'answer' => 'Acceptable proof includes screenshots, wallet transactions, platform interactions, or any verifiable activity related to the project.',
            ],
            [
                'question' => 'How are rewards ($REX) calculated?',
                'answer' => 'Rewards are based on review quality, proof validity, and scoring metrics. Higher-quality reviews receive better rewards.',
            ],
            [
                'question' => 'When will my review be approved?',
                'answer' => 'Reviews are manually reviewed by admins. Approval time may vary depending on moderation workload.',
            ],
            [
                'question' => 'Why was my review rejected or flagged?',
                'answer' => 'Reviews may be rejected if they lack proper proof, contain misleading information, or violate platform guidelines.',
            ],
            [
                'question' => 'Can I edit my review after submission?',
                'answer' => 'Once submitted, reviews cannot be edited until reviewed. You may need to resubmit if rejected.',
            ],
            [
                'question' => 'What is the level system?',
                'answer' => 'Users progress through levels such as Beginner, Pro, and Expert based on activity, quality reviews, and trust score.',
            ],
            [
                'question' => 'What are badges?',
                'answer' => 'Badges are achievements awarded for consistent quality contributions and platform engagement.',
            ],
            [
                'question' => 'Can I refer others?',
                'answer' => 'Yes, CoinRex includes a referral system where you can invite others and earn benefits.',
            ],
        ],
    ],
    [
        'slug' => 'developers',
        'icon' => 'fa-code',
        'title' => 'Developer FAQs',
        'description' => 'The DevHub workflow, verification rules, project submissions, and moderation expectations.',
        'items' => [
            [
                'question' => 'How can I submit my project?',
                'answer' => 'After logging in, access DevHub, complete identity verification, and submit your project using the submission form.',
            ],
            [
                'question' => 'Why is identity verification required?',
                'answer' => 'Verification ensures that only legitimate developers can list projects, reducing scams and fake listings.',
            ],
            [
                'question' => 'What verification methods are supported?',
                'answer' => 'Verification can be done through social media proof or website meta-tag validation.',
            ],
            [
                'question' => 'How long does project approval take?',
                'answer' => 'Approval time depends on review workload and verification checks. It may take some time for full validation.',
            ],
            [
                'question' => 'Why was my project rejected?',
                'answer' => 'Projects may be rejected due to insufficient information, failed verification, or policy violations.',
            ],
            [
                'question' => 'Can I update my project?',
                'answer' => 'Updates may be allowed after approval, subject to moderation rules.',
            ],
            [
                'question' => 'Can I submit multiple projects?',
                'answer' => 'Yes, verified developers can submit multiple projects.',
            ],
            [
                'question' => 'How do I track my project status?',
                'answer' => 'You can monitor your project status from the DevHub dashboard.',
            ],
        ],
    ],
    [
        'slug' => 'moderation',
        'icon' => 'fa-shield-heart',
        'title' => 'Moderation and Trust FAQs',
        'description' => 'How CoinRex reviews content, blocks abuse, and protects trust across the platform.',
        'items' => [
            [
                'question' => 'Why are reviews not instantly visible?',
                'answer' => 'All reviews go through moderation to ensure authenticity and quality before being published.',
            ],
            [
                'question' => 'How does CoinRex prevent fake reviews?',
                'answer' => 'CoinRex reduces fake activity by requiring proof, applying manual moderation, and using trust scoring mechanisms.',
            ],
            [
                'question' => 'What happens to flagged content?',
                'answer' => 'Flagged reviews are re-evaluated by admins and may be removed if found invalid.',
            ],
            [
                'question' => 'How is trust score calculated?',
                'answer' => 'Trust score is based on review quality, user history, and moderation outcomes.',
            ],
        ],
    ],
    [
        'slug' => 'rewards',
        'icon' => 'fa-coins',
        'title' => 'Rewards and Economy FAQs',
        'description' => 'The basics of $REX, earning rules, and what approved participation unlocks.',
        'items' => [
            [
                'question' => 'What is $REX?',
                'answer' => '$REX is the internal reward token earned by users for submitting valid and high-quality reviews.',
            ],
            [
                'question' => 'How do I earn $REX?',
                'answer' => 'You earn rewards by submitting approved reviews with valid proof.',
            ],
            [
                'question' => 'Can I withdraw $REX?',
                'answer' => 'Withdrawal or usage depends on platform features and policies and may expand over time.',
            ],
            [
                'question' => 'Do rejected reviews earn rewards?',
                'answer' => 'No. Only approved reviews are eligible for rewards.',
            ],
        ],
    ],
    [
        'slug' => 'security',
        'icon' => 'fa-lock',
        'title' => 'Account and Security FAQs',
        'description' => 'Password resets, suspicious activity, privacy, and account control.',
        'items' => [
            [
                'question' => 'I forgot my password. What should I do?',
                'answer' => 'Use the "Forgot Password" option to reset your password via OTP verification.',
            ],
            [
                'question' => 'How is my data protected?',
                'answer' => 'CoinRex uses secure authentication and data handling practices to protect user information.',
            ],
            [
                'question' => 'Can I delete my account?',
                'answer' => 'Account deletion may be requested through support or future settings features.',
            ],
            [
                'question' => 'What should I do if I notice suspicious activity?',
                'answer' => 'Report it immediately through the contact or support section.',
            ],
        ],
    ],
    [
        'slug' => 'support',
        'icon' => 'fa-life-ring',
        'title' => 'Support and Technical FAQs',
        'description' => 'Common access issues, restrictions, and the fastest way to get help.',
        'items' => [
            [
                'question' => 'Why can\'t I access certain features?',
                'answer' => 'Some features are restricted based on account verification, level, or role.',
            ],
            [
                'question' => 'Why is my account restricted?',
                'answer' => 'Accounts may be restricted due to policy violations or suspicious activity.',
            ],
            [
                'question' => 'How can I contact support?',
                'answer' => 'You can reach out through the contact page or support email.',
            ],
        ],
    ],
];

$faq_total_questions = 0;
foreach ($faq_sections as $section) {
    $faq_total_questions += count($section['items']);
}
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/faq.css">

<main class="faq-main">
    <section class="faq-hero">
        <div class="faq-shell">
            <div class="faq-hero-grid">
                <div class="faq-hero-copy">
                    <div class="faq-badge">
                        <i class="fas fa-question-circle"></i>
                        <span>Help Center</span>
                    </div>
                    <h1>Frequently Asked <span>Questions</span></h1>
                    <p>
                        Everything users, reviewers, developers, and project owners need to know
                        about CoinRex in one polished, easy-to-scan place.
                    </p>
                    <div class="faq-hero-stats">
                        <div class="faq-stat">
                            <strong><?php echo number_format(count($faq_sections)); ?></strong>
                            <span>Categories</span>
                        </div>
                        <div class="faq-stat">
                            <strong><?php echo number_format($faq_total_questions); ?></strong>
                            <span>Answered Questions</span>
                        </div>
                        <div class="faq-stat">
                            <strong><?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Support Contact</span>
                        </div>
                    </div>
                </div>

                <div class="faq-hero-panel">
                    <div class="faq-panel-card">
                        <span class="faq-panel-kicker">Quick Guidance</span>
                        <h2>Find answers by role</h2>
                        <ul>
                            <li><i class="fas fa-check"></i> New users can start in General Questions</li>
                            <li><i class="fas fa-check"></i> Reviewers can jump into proof and rewards</li>
                            <li><i class="fas fa-check"></i> Developers can use the DevHub section</li>
                            <li><i class="fas fa-check"></i> Security and support answers are grouped separately</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="faq-hero-wave" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#07111f" fill-opacity="1" d="M0,256L34.3,240C68.6,224,137,192,206,165.3C274.3,139,343,117,411,133.3C480,149,549,203,617,202.7C685.7,203,754,149,823,122.7C891.4,96,960,96,1029,122.7C1097.1,149,1166,203,1234,224C1302.9,245,1371,235,1406,229.3L1440,224L1440,320L1405.7,320C1371.4,320,1303,320,1234,320C1165.7,320,1097,320,1029,320C960,320,891,320,823,320C754.3,320,686,320,617,320C548.6,320,480,320,411,320C342.9,320,274,320,206,320C137.1,320,69,320,34,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <section class="faq-browser">
        <div class="faq-shell">
            <div class="faq-browser-card faq-reveal">
                <div class="faq-browser-head">
                    <div class="faq-browser-copy">
                        <span class="faq-controls-kicker">Browse Faster</span>
                        <h2>Choose a category</h2>
                        <p>Jump straight to the area you need with a cleaner category browser designed for both desktop and mobile.</p>
                    </div>
                    <div class="faq-browser-stats" aria-label="FAQ overview">
                        <div class="faq-browser-stat">
                            <strong><?php echo number_format(count($faq_sections)); ?></strong>
                            <span>Categories</span>
                        </div>
                        <div class="faq-browser-stat">
                            <strong><?php echo number_format($faq_total_questions); ?></strong>
                            <span>Total Answers</span>
                        </div>
                    </div>
                </div>

                <div class="faq-filter-grid" role="tablist" aria-label="FAQ categories">
                    <button type="button" class="faq-filter-pill is-active" data-filter="all">
                        <span class="faq-filter-pill-title">All Questions</span>
                        <span class="faq-filter-pill-meta"><?php echo number_format($faq_total_questions); ?> answers</span>
                    </button>
                    <?php foreach ($faq_sections as $section): ?>
                        <button
                            type="button"
                            class="faq-filter-pill"
                            data-filter="<?php echo htmlspecialchars($section['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                            title="<?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span class="faq-filter-pill-title"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="faq-filter-pill-meta"><?php echo number_format(count($section['items'])); ?> answer(s)</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-sections">
        <div class="faq-shell">
            <div class="faq-content">
                <div class="faq-grid">
                    <?php foreach ($faq_sections as $section_index => $section): ?>
                        <section class="faq-category-card faq-reveal" data-category="<?php echo htmlspecialchars($section['slug'], ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($section['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="faq-category-head">
                                <div class="faq-category-icon">
                                    <i class="fas <?php echo htmlspecialchars($section['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <div class="faq-category-copy">
                                    <span><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <h3><?php echo htmlspecialchars($section['description'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                            </div>

                            <div class="faq-accordion">
                                <?php foreach ($section['items'] as $item_index => $item): ?>
                                    <?php
                                    $is_open = $section_index === 0 && $item_index === 0;
                                    $item_id = 'faq-' . $section['slug'] . '-' . $item_index;
                                    ?>
                                    <article class="faq-item<?php echo $is_open ? ' is-open' : ''; ?>">
                                        <button
                                            type="button"
                                            class="faq-question"
                                            aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                                            aria-controls="<?php echo htmlspecialchars($item_id, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <span><?php echo htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <div class="faq-answer" id="<?php echo htmlspecialchars($item_id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="faq-answer-inner">
                                                <p><?php echo htmlspecialchars($item['answer'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-support-strip">
        <div class="faq-shell">
            <div class="faq-support-card faq-reveal">
                <div>
                    <span class="faq-controls-kicker">Still Need Help?</span>
                    <h2>Reach the CoinRex team directly</h2>
                    <p>If your question is not covered here yet, use the support channel and we will help you out.</p>
                </div>
                <div class="faq-support-actions">
                    <a href="<?php echo BASE_URL; ?>/contact.php" class="faq-btn faq-btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        <span>Contact Support</span>
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?>" class="faq-btn faq-btn-secondary">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    (function () {
        const faqItems = document.querySelectorAll('.faq-item');
        const filterButtons = document.querySelectorAll('.faq-filter-pill');
        const categoryCards = document.querySelectorAll('.faq-category-card');
        const revealItems = document.querySelectorAll('.faq-reveal');

        const updateAccordion = function (item, shouldOpen) {
            const trigger = item.querySelector('.faq-question');
            const panel = item.querySelector('.faq-answer');

            if (!trigger || !panel) {
                return;
            }

            item.classList.toggle('is-open', shouldOpen);
            trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            panel.style.maxHeight = shouldOpen ? panel.scrollHeight + 'px' : '0px';
        };

        faqItems.forEach(function (item) {
            const trigger = item.querySelector('.faq-question');
            const panel = item.querySelector('.faq-answer');

            if (!trigger || !panel) {
                return;
            }

            panel.style.maxHeight = item.classList.contains('is-open') ? panel.scrollHeight + 'px' : '0px';

            trigger.addEventListener('click', function () {
                const isOpen = item.classList.contains('is-open');

                faqItems.forEach(function (otherItem) {
                    if (otherItem !== item) {
                        updateAccordion(otherItem, false);
                    }
                });

                updateAccordion(item, !isOpen);
            });
        });

        const syncFilterButtons = function (activeFilter) {
            filterButtons.forEach(function (otherButton) {
                const buttonFilter = otherButton.getAttribute('data-filter') || 'all';
                otherButton.classList.toggle('is-active', buttonFilter === activeFilter);
            });
        };

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const filter = button.getAttribute('data-filter') || 'all';

                syncFilterButtons(filter);

                categoryCards.forEach(function (card) {
                    const category = card.getAttribute('data-category');
                    const isVisible = filter === 'all' || filter === category;
                    card.hidden = !isVisible;
                });

                if (filter !== 'all') {
                    const targetCard = document.querySelector('.faq-category-card[data-category="' + filter + '"]');
                    if (targetCard) {
                        targetCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.18 });

            revealItems.forEach(function (item) {
                observer.observe(item);
            });
        } else {
            revealItems.forEach(function (item) {
                item.classList.add('is-visible');
            });
        }
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
