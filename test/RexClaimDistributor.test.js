const assert = require("node:assert/strict");
const { ethers } = require("hardhat");

const CLAIM_FEE = ethers.parseEther("0.01");

async function signClaim(signer, contract, claimant, snapshotId, amount, deadline) {
  const network = await ethers.provider.getNetwork();
  return signer.signTypedData(
    {
      name: "CoinRex Claim Distributor",
      version: "1",
      chainId: network.chainId,
      verifyingContract: await contract.getAddress(),
    },
    {
      Claim: [
        { name: "claimant", type: "address" },
        { name: "snapshotId", type: "uint256" },
        { name: "amount", type: "uint256" },
        { name: "deadline", type: "uint256" },
      ],
    },
    {
      claimant,
      snapshotId,
      amount,
      deadline,
    }
  );
}

describe("RexClaimDistributor", function () {
  async function deployFixture() {
    const [owner, reserve, claimSigner, claimant, other] = await ethers.getSigners();

    const Token = await ethers.getContractFactory("CoinRexToken");
    const token = await Token.deploy(owner.address);
    await token.waitForDeployment();

    const Distributor = await ethers.getContractFactory("RexClaimDistributor");
    const distributor = await Distributor.deploy(
      await token.getAddress(),
      reserve.address,
      claimSigner.address,
      CLAIM_FEE,
      owner.address
    );
    await distributor.waitForDeployment();

    await token.transfer(await distributor.getAddress(), ethers.parseUnits("10000", 18));

    return { token, distributor, owner, reserve, claimSigner, claimant, other };
  }

  it("deploys with the expected configuration", async function () {
    const { token, distributor, reserve, claimSigner, owner } = await deployFixture();

    assert.equal(await distributor.rexToken(), await token.getAddress());
    assert.equal(await distributor.reserveWallet(), reserve.address);
    assert.equal(await distributor.claimSigner(), claimSigner.address);
    assert.equal(await distributor.claimFee(), CLAIM_FEE);
    assert.equal(await distributor.owner(), owner.address);
  });

  it("claims REX and forwards the processing fee", async function () {
    const { token, distributor, reserve, claimSigner, claimant } = await deployFixture();
    const snapshotId = 101n;
    const amount = ethers.parseUnits("125", 18);
    const deadline = BigInt((await ethers.provider.getBlock("latest")).timestamp + 3600);
    const signature = await signClaim(claimSigner, distributor, claimant.address, snapshotId, amount, deadline);
    const reserveBefore = await ethers.provider.getBalance(reserve.address);

    await distributor.connect(claimant).claim(snapshotId, amount, deadline, signature, { value: CLAIM_FEE });

    assert.equal(await token.balanceOf(claimant.address), amount);
    assert.equal(await distributor.claimedSnapshots(snapshotId), true);
    assert.equal(await ethers.provider.getBalance(reserve.address), reserveBefore + CLAIM_FEE);
  });

  it("rejects replayed snapshot claims", async function () {
    const { distributor, claimSigner, claimant } = await deployFixture();
    const snapshotId = 202n;
    const amount = ethers.parseUnits("20", 18);
    const deadline = BigInt((await ethers.provider.getBlock("latest")).timestamp + 3600);
    const signature = await signClaim(claimSigner, distributor, claimant.address, snapshotId, amount, deadline);

    await distributor.connect(claimant).claim(snapshotId, amount, deadline, signature, { value: CLAIM_FEE });

    await assert.rejects(
      distributor.connect(claimant).claim(snapshotId, amount, deadline, signature, { value: CLAIM_FEE }),
      /already claimed|reverted/
    );
  });

  it("rejects invalid fees and signatures", async function () {
    const { distributor, other, claimant } = await deployFixture();
    const snapshotId = 303n;
    const amount = ethers.parseUnits("30", 18);
    const deadline = BigInt((await ethers.provider.getBlock("latest")).timestamp + 3600);
    const badSignature = await signClaim(other, distributor, claimant.address, snapshotId, amount, deadline);

    await assert.rejects(
      distributor.connect(claimant).claim(snapshotId, amount, deadline, badSignature, { value: CLAIM_FEE }),
      /invalid signature|reverted/
    );

    await assert.rejects(
      distributor.connect(claimant).claim(snapshotId, amount, deadline, badSignature, { value: ethers.parseEther("0.02") }),
      /invalid fee|reverted/
    );
  });

  it("allows owner updates", async function () {
    const { distributor, reserve, other } = await deployFixture();

    await distributor.setClaimFee(ethers.parseEther("0.02"));
    await distributor.setClaimSigner(other.address);
    await distributor.setReserveWallet(reserve.address);

    assert.equal(await distributor.claimFee(), ethers.parseEther("0.02"));
    assert.equal(await distributor.claimSigner(), other.address);
    assert.equal(await distributor.reserveWallet(), reserve.address);
  });
});
