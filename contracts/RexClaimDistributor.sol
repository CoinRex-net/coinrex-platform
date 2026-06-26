// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/utils/cryptography/EIP712.sol";

contract RexClaimDistributor is Ownable, ReentrancyGuard, EIP712 {
    using SafeERC20 for IERC20;

    IERC20 public immutable rexToken;
    address public reserveWallet;
    address public claimSigner;
    uint256 public claimFee;

    mapping(uint256 => bool) public claimedSnapshots;

    bytes32 public constant CLAIM_TYPEHASH =
        keccak256("Claim(address claimant,uint256 snapshotId,uint256 amount,uint256 deadline)");

    event ClaimSignerUpdated(address indexed oldSigner, address indexed newSigner);
    event ClaimFeeUpdated(uint256 oldFee, uint256 newFee);
    event ReserveWalletUpdated(address indexed oldReserveWallet, address indexed newReserveWallet);
    event Claimed(
        address indexed claimant,
        uint256 indexed snapshotId,
        uint256 amount,
        uint256 fee,
        address indexed reserveWallet
    );

    constructor(
        address rexTokenAddress,
        address initialReserveWallet,
        address initialClaimSigner,
        uint256 initialClaimFee,
        address initialOwner
    ) Ownable(initialOwner) EIP712("CoinRex Claim Distributor", "1") {
        require(rexTokenAddress != address(0), "RexClaimDistributor: zero token");
        require(initialReserveWallet != address(0), "RexClaimDistributor: zero reserve");
        require(initialClaimSigner != address(0), "RexClaimDistributor: zero signer");
        require(initialOwner != address(0), "RexClaimDistributor: zero owner");

        rexToken = IERC20(rexTokenAddress);
        reserveWallet = initialReserveWallet;
        claimSigner = initialClaimSigner;
        claimFee = initialClaimFee;
    }

    function claim(
        uint256 snapshotId,
        uint256 amount,
        uint256 deadline,
        bytes calldata signature
    ) external payable nonReentrant {
        require(block.timestamp <= deadline, "RexClaimDistributor: expired claim");
        require(!claimedSnapshots[snapshotId], "RexClaimDistributor: already claimed");
        require(msg.value == claimFee, "RexClaimDistributor: invalid fee");
        require(amount > 0, "RexClaimDistributor: zero amount");

        bytes32 structHash = keccak256(
            abi.encode(CLAIM_TYPEHASH, msg.sender, snapshotId, amount, deadline)
        );
        address recoveredSigner = ECDSA.recover(_hashTypedDataV4(structHash), signature);
        require(recoveredSigner == claimSigner, "RexClaimDistributor: invalid signature");

        claimedSnapshots[snapshotId] = true;

        (bool sent, ) = reserveWallet.call{value: msg.value}("");
        require(sent, "RexClaimDistributor: fee transfer failed");

        rexToken.safeTransfer(msg.sender, amount);

        emit Claimed(msg.sender, snapshotId, amount, msg.value, reserveWallet);
    }

    function setClaimSigner(address newClaimSigner) external onlyOwner {
        require(newClaimSigner != address(0), "RexClaimDistributor: zero signer");
        address oldSigner = claimSigner;
        claimSigner = newClaimSigner;
        emit ClaimSignerUpdated(oldSigner, newClaimSigner);
    }

    function setClaimFee(uint256 newClaimFee) external onlyOwner {
        uint256 oldFee = claimFee;
        claimFee = newClaimFee;
        emit ClaimFeeUpdated(oldFee, newClaimFee);
    }

    function setReserveWallet(address newReserveWallet) external onlyOwner {
        require(newReserveWallet != address(0), "RexClaimDistributor: zero reserve");
        address oldReserveWallet = reserveWallet;
        reserveWallet = newReserveWallet;
        emit ReserveWalletUpdated(oldReserveWallet, newReserveWallet);
    }

    function rescueRex(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "RexClaimDistributor: zero recipient");
        rexToken.safeTransfer(to, amount);
    }
}
