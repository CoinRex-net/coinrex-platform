<!-- Mystery Box Modal (Day 10) -->
<div class="taskhub-mystery-modal" id="taskhubMysteryModal" hidden>
    <div class="taskhub-mystery-backdrop" data-mystery-close></div>
    <div class="taskhub-mystery-dialog" role="dialog" aria-modal="true" aria-labelledby="taskhubMysteryTitle">
        <div class="taskhub-mystery-confetti" id="mysteryConfetti"></div>
        <div class="taskhub-mystery-content">
            <button type="button" class="taskhub-mystery-close" data-mystery-close aria-label="Close mystery box"><i class="fas fa-times"></i></button>

            <div class="taskhub-mystery-head">
                <span class="taskhub-mystery-kicker">Day 10 Reward</span>
                <h2 id="taskhubMysteryTitle">Mystery Box</h2>
                <p>Choose one sealed box. Your reward is verified on claim.</p>
            </div>

            <div class="taskhub-mystery-boxes" id="mysteryBoxes">
                <div class="taskhub-mystery-box" data-box-index="0">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-lid" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-body" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-icon">&#127873;</span>
                            <span class="taskhub-mystery-box-label">Box 1</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="0">Claim to reveal</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="1">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-lid" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-body" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-icon">&#127873;</span>
                            <span class="taskhub-mystery-box-label">Box 2</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="1">Claim to reveal</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="2">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-glow"></span>
                            <span class="taskhub-mystery-box-lid" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-body" aria-hidden="true"></span>
                            <span class="taskhub-mystery-box-icon">&#127873;</span>
                            <span class="taskhub-mystery-box-label">Box 3</span>
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
            </div>
        </div>
    </div>
</div>
