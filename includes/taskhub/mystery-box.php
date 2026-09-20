<!-- Mystery Box Modal (Day 10) -->
<div class="taskhub-mystery-modal" id="taskhubMysteryModal" hidden>
    <div class="taskhub-mystery-backdrop" data-mystery-close></div>
    <div class="taskhub-mystery-dialog" role="dialog" aria-modal="true" aria-labelledby="taskhubMysteryTitle">
        <div class="taskhub-mystery-confetti" id="mysteryConfetti"></div>
        <div class="taskhub-mystery-content">
            <button type="button" class="taskhub-mystery-close" data-mystery-close aria-label="Close mystery box"><i class="fas fa-times"></i></button>

            <div class="taskhub-mystery-head">
                <div class="taskhub-mystery-title-row">
                    <span class="taskhub-mystery-head-icon"><i class="fas fa-gift"></i></span>
                    <span class="taskhub-mystery-kicker"><i class="fas fa-crown"></i> Final Mission · 10/10</span>
                </div>
                <h2 id="taskhubMysteryTitle">Choose Your Final Reward</h2>
                <p>Pick one sealed box to reveal your surprise $REX reward.</p>
                <div class="taskhub-mystery-perks" aria-label="Reward benefits">
                    <span><i class="fas fa-shield-halved"></i> Server verified</span>
                    <span><i class="fas fa-bolt"></i> Instant $REX</span>
                    <span><i class="fas fa-gem"></i> PRO unlock</span>
                </div>
            </div>

            <div class="taskhub-mystery-prompt"><span></span> Tap a box to reveal your reward <span></span></div>

            <div class="taskhub-mystery-boxes" id="mysteryBoxes">
                <div class="taskhub-mystery-box" data-box-index="0" role="button" tabindex="0" aria-label="Choose mystery box 1">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-icon" aria-hidden="true">&#127873;</span>
                            <span class="taskhub-mystery-box-label"><small>Pick</small> Box 1</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="0">Claim to reveal</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="1" role="button" tabindex="0" aria-label="Choose mystery box 2">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-icon" aria-hidden="true">&#127873;</span>
                            <span class="taskhub-mystery-box-label"><small>Pick</small> Box 2</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="1">Claim to reveal</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="2" role="button" tabindex="0" aria-label="Choose mystery box 3">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-icon" aria-hidden="true">&#127873;</span>
                            <span class="taskhub-mystery-box-label"><small>Pick</small> Box 3</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="2">Claim to reveal</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="taskhub-mystery-result" id="mysteryResult" hidden>
                <div class="taskhub-mystery-result-icon">$REX</div>
                <strong id="mysteryResultText">Box selected</strong>
                <p id="mysteryResultSub">Claim now to reveal your server-verified reward.</p>

                <div id="mysteryProUnlock" class="taskhub-mystery-pro-unlock" hidden>
                    <div class="taskhub-mystery-pro-icon">PRO</div>
                    <div class="taskhub-mystery-pro-text">
                        <strong>PRO Level Unlocked</strong>
                        <p>Review rewards, claims, BoostHub priority, analytics, and exclusive missions are now available.</p>
                    </div>
                </div>

                <div id="mysteryPartialMsg" class="taskhub-mystery-partial" hidden>
                    <div class="taskhub-mystery-partial-icon">10/10</div>
                    <div class="taskhub-mystery-partial-text">
                        <strong>LearnHub Progress</strong>
                        <p>Finish the LearnHub mission to unlock PRO automatically.</p>
                        <ul class="taskhub-mystery-requirements" id="mysteryRequirementList"></ul>
                    </div>
                </div>
            </div>

            <div class="taskhub-mystery-actions">
                <button type="button" class="primary-btn" id="mysteryClaimBtn" disabled>Choose a Box</button>
                <span class="taskhub-mystery-safe-note"><i class="fas fa-lock"></i> One choice · Secure reward claim</span>
            </div>
        </div>
    </div>
</div>
