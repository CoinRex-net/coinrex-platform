const assert = require("node:assert/strict");
const { ethers } = require("hardhat");

describe("CoinRexToken", function () {
  async function deployTokenFixture() {
    const [deployer, recipient] = await ethers.getSigners();
    const Token = await ethers.getContractFactory("CoinRexToken");
    const token = await Token.deploy(deployer.address);
    await token.waitForDeployment();

    return { token, deployer, recipient };
  }

  it("uses the CoinRex token identity", async function () {
    const { token } = await deployTokenFixture();

    assert.equal(await token.name(), "CoinRex Token");
    assert.equal(await token.symbol(), "REX");
    assert.equal(await token.decimals(), 18n);
  });

  it("mints 1B REX to the deployer", async function () {
    const { token, deployer } = await deployTokenFixture();
    const expectedSupply = ethers.parseUnits("1000000000", 18);

    assert.equal(await token.totalSupply(), expectedSupply);
    assert.equal(await token.INITIAL_SUPPLY(), expectedSupply);
    assert.equal(await token.balanceOf(deployer.address), expectedSupply);
  });

  it("supports standard ERC-20 transfers", async function () {
    const { token, deployer, recipient } = await deployTokenFixture();
    const amount = ethers.parseUnits("25", 18);

    await token.transfer(recipient.address, amount);

    assert.equal(await token.balanceOf(recipient.address), amount);
    assert.equal(
      await token.balanceOf(deployer.address),
      (await token.totalSupply()) - amount
    );
  });

  it("rejects a zero initial recipient", async function () {
    const Token = await ethers.getContractFactory("CoinRexToken");

    await assert.rejects(
      Token.deploy(ethers.ZeroAddress),
      /zero recipient|reverted/
    );
  });
});
