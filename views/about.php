<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';

ui_render_head(
    'About | Yunus Emre Vurgun',
    'Developer and IT specialist based in Istanbul, specializing in computational intelligence and operational technology.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('About'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Profile</p>
            <h1 class="ui-section-title">Developer & IT Specialist</h1>
            <p class="ui-section-text">Based in Istanbul, working at the intersection of computational intelligence and operational technology.</p>
        </section>

        <section class="ui-section">
            <div class="ui-about-grid">
                <div class="ui-command-center">
                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Summary</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <p>Developer and IT specialist from Istanbul with a focus on AI and operational technology.</p>
                                <p>Building systems that are architecturally robust and computationally efficient. I prioritize time-resistant fundamentals and mathematical soundness over industry trends.</p>
                                <p>From CPU-optimized neural network proof-of-concepts to industrial automation — I build for the internal logic, not the surface-level implementation.</p>
                                <p>Currently working at ASP Otomasyon A.Ş., building and maintaining internal systems for industrial operations.</p>
                            </div>
                        </div>
                    </article>

                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Education</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <ul class="ui-timeline">
                                    <li class="ui-timeline-item"><strong>Information Technology — Bachelor's</strong><br>Illinois Institute of Technology | 2023 — present</li>
                                    <li class="ui-timeline-item"><strong>Computer Programming — A.S.</strong><br>Beykoz University | 2020 — 2023</li>
                                    <li class="ui-timeline-item"><strong>Management Information Systems — Bachelor's</strong><br>Anadolu University | 2024 — present</li>
                                    <li class="ui-timeline-item"><strong>English Language Teaching — BA</strong><br>Bahcesehir University | 2018 — 2020</li>
                                </ul>
                            </div>
                        </div>
                    </article>

                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Work History</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <ul class="ui-timeline">
                                    <li class="ui-timeline-item"><strong>Software Developer & IT Specialist</strong><br>ASP Otomasyon A.Ş. | 2022 — present<br>Building and maintaining internal systems for industrial operations — from business tools to IT/OT infrastructure.</li>
                                    <li class="ui-timeline-item"><strong>Financial Assistant</strong><br>ASP Otomasyon A.Ş. | 2019 — 2022<br>Started in finance and administration. The transition into technical work shaped my approach to building practical solutions.</li>
                                </ul>
                            </div>
                        </div>
                    </article>

                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Certifications</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <ul class="ui-card-list">
                                    <li>IELTS Academic — Score: 7.5</li>
                                    <li>Fortinet OT Security Architect Training</li>
                                    <li>Introduction to Generative AI — Google Cloud</li>
                                    <li>AI Essentials — Intel</li>
                                    <li>Computational Vision — University of Colorado Boulder</li>
                                    <li>Network Support and Security — Cisco</li>
                                    <li>Technologies for AI — Politecnico di Milano</li>
                                </ul>
                            </div>
                        </div>
                    </article>

                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Connect</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <ul class="ui-card-list">
                                    <li><strong>YouTube</strong><br><a href="https://www.youtube.com/@yunusemrevurgun1" target="_blank" rel="noopener noreferrer">@yunusemrevurgun1</a></li>
                                    <li><strong>GitHub</strong><br><a href="https://github.com/yunusemrejr" target="_blank" rel="noopener noreferrer">@yunusemrejr</a></li>
                                    <li><strong>LinkedIn</strong><br><a href="https://linkedin.com/in/yunus-emre-vurgun-49ba9a177" target="_blank" rel="noopener noreferrer">Profile</a></li>
                                    <li><strong>Mastodon</strong><br><a href="https://mastodon.social/@yunusemrevurgn" target="_blank" rel="noopener noreferrer">@yunusemrevurgn</a></li>
                                    <li><strong>Bluesky</strong><br><a href="https://bsky.app/profile/yunusemrevurgun.bsky.social" target="_blank" rel="noopener noreferrer">@yunusemrevurgun</a></li>
                                    <li><strong>X / Twitter</strong><br><a href="https://x.com/yemrevu" target="_blank" rel="noopener noreferrer">@yemrevu</a></li>
                                    <li><strong>Instagram</strong><br><a href="https://instagram.com/yemrevu" target="_blank" rel="noopener noreferrer">@yemrevu</a></li>
                                </ul>
                            </div>
                        </div>
                    </article>

                    <article class="ui-command-card">
                        <button class="ui-command-trigger" type="button" aria-expanded="false">Legal</button>
                        <div class="ui-command-panel">
                            <div class="ui-command-content">
                                <ul class="ui-card-list">
                                    <li><a href="<?= FULL_BASE_PATH ?>privacy">Privacy Policy</a></li>
                                    <li><a href="<?= FULL_BASE_PATH ?>terms">Terms of Use</a></li>
                                    <li><a href="<?= FULL_BASE_PATH ?>cookies">Cookie Policy</a></li>
                                </ul>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="ui-photo-box" style="position: sticky; top: calc(var(--navbar-height) + 2rem);">
                    <div class="ui-profile-frame">
                        <img
                            class="ui-profile-image ui-portrait-crossfade-base"
                            src="<?= FULL_BASE_PATH ?>assets/images/portrait-3.png"
                            alt="Yunus Emre Vurgun"
                            loading="eager"
                        >
                        <img
                            class="ui-profile-image ui-portrait-crossfade-alt"
                            src="<?= FULL_BASE_PATH ?>assets/images/real-pfp.png"
                            alt="Yunus Emre Vurgun"
                            loading="eager"
                        >
                        <img
                            class="ui-profile-image ui-portrait-crossfade-tert"
                            src="<?= FULL_BASE_PATH ?>assets/images/yunus-emre-vurgun-portrait.jpg"
                            alt="Yunus Emre Vurgun"
                            loading="eager"
                        >
                    </div>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                        <p style="font-size: var(--text-sm); color: var(--color-text-secondary); line-height: 1.6;">
                            Based in Istanbul, Turkey. Currently building systems at ASP Otomasyon A.Ş.
                        </p>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
