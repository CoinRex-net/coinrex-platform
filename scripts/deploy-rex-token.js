const fs = require("fs");
const path = require("path");
const hre = require("hardhat");

async function main() {
  const [deployer] = await hre.ethers.getSigners();

  if (!deployer) {
    throw new Error("No deployer account configured. Set POLYGON_AMOY_PRIVATE_KEY or POLYGON_MAINNET_PRIVATE_KEY in .env.");
  }

  const network = await hre.ethers.provider.getNetwork();
  const Token = await hre.ethers.getContractFactory("CoinRexToken");
  const token = await Token.deploy(deployer.address);
  await token.waitForDeployment();

  const contractAddress = await token.getAddress();
  const decimals = await token.decimals();
  const initialSupply = await token.INITIAL_SUPPLY();
  const deployerBalance = await token.balanceOf(deployer.address);

  const deployment = {
    contractName: "CoinRexToken",
    tokenName: await token.name(),
    symbol: await token.symbol(),
    decimals: Number(decimals),
    initialSupply: initialSupply.toString(),
    initialSupplyFormatted: hre.ethers.formatUnits(initialSupply, decimals),
    deployer: deployer.address,
    deployerBalance: deployerBalance.toString(),
    contractAddress,
    network: hre.network.name,
    chainId: Number(network.chainId),
    deployedAt: new Date().toISOString(),
  };

  const deploymentsDir = path.join(__dirname, "..", "deployments");
  fs.mkdirSync(deploymentsDir, { recursive: true });

  const fileName = hre.network.name === "amoy"
    ? "polygon-amoy-rex-token.json"
    : (hre.network.name === "polygon" ? "polygon-rex-token.json" : `${hre.network.name}-rex-token.json`);

  fs.writeFileSync(
    path.join(deploymentsDir, fileName),
    `${JSON.stringify(deployment, null, 2)}\n`,
    "utf8"
  );

  console.log("CoinRex Token deployed");
  console.log(`Network: ${deployment.network} (${deployment.chainId})`);
  console.log(`Deployer: ${deployment.deployer}`);
  console.log(`Contract: ${deployment.contractAddress}`);
  console.log(`Initial supply: ${deployment.initialSupplyFormatted} REX`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
