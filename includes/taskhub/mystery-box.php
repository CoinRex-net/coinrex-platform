<!-- Mystery Box Modal (Day 10) -->
<div class="taskhub-mystery-modal" id="taskhubMysteryModal" hidden>
    <div class="taskhub-mystery-backdrop"></div>
    <div class="taskhub-mystery-dialog">
        <div class="taskhub-mystery-confetti" id="mysteryConfetti"></div>
        <div class="taskhub-mystery-content">
            <span class="taskhub-mystery-emoji">🎉</span>
            <h2>Congratulations!</h2>
            <p>You've completed all 10 days! Choose your mystery box to reveal your reward.</p>
            <div class="taskhub-mystery-boxes" id="mysteryBoxes">
                <div class="taskhub-mystery-box" data-box-index="0">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-icon">🎁</span>
                            <span class="taskhub-mystery-box-label">Box 1</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="0">0 $REX</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="1">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-icon">🎁</span>
                            <span class="taskhub-mystery-box-label">Box 2</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="1">0 $REX</span>
                        </div>
                    </div>
                </div>
                <div class="taskhub-mystery-box" data-box-index="2">
                    <div class="taskhub-mystery-box-inner">
                        <div class="taskhub-mystery-box-front">
                            <span class="taskhub-mystery-box-icon">🎁</span>
                            <span class="taskhub-mystery-box-label">Box 3</span>
                        </div>
                        <div class="taskhub-mystery-box-back">
                            <span class="taskhub-mystery-box-reward" data-box-reward="2">0 $REX</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="taskhub-mystery-result" id="mysteryResult" hidden>
                <div class="taskhub-mystery-result-icon">🎊</div>
                <strong id="mysteryResultText">You won 15 $REX!</strong>
                <p id="mysteryResultSub">Reward has been added to your balance.</p>
            </div>
            <button type="button" class="primary-btn" id="mysteryClaimBtn" hidden>Claim Reward</button>
        </div>
    </div>
</div>
