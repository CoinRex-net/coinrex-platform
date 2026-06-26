const fs = require("fs");
const path = require("path");
const hre = require("hardhat");

const DEFAULT_FUND_AMOUNT_REX = "1000000";

function readDeployment(fileName) {
  const filePath = path.join(__dirname, "..", "deployments", fileName);
  if (!fs.existsSync(filePath)) {
    throw new Error(`Deployment metadata not found: ${filePath}`);
  }

  return JSON.parse(fs.readFileSync(filePath, "utf8"));
}

async function main() {
  const tokenFile = hre.network.name === "amoy"
    ? "polygon-amoy-rex-token.json"
    : `${hre.network.name}-rex-token.json`;
  const distributorFile = hre.network.name === "amoy"
    ? "polygon-amoy-rex-claim-distributor.json"
    : `${hre.network.name}-rex-claim-distributor.json`;

  const tokenDeployment = readDeployment(tokenFile);
  const distributorDeployment = readDeployment(distributorFile);
  const amount = hre.ethers.parseUnits(
    process.env.REX_DISTRIBUTOR_FUND_AMOUNT || DEFAULT_FUND_AMOUNT_REX,
    tokenDeployment.decimals || 18
  );

  const token = await hre.ethers.getContractAt("CoinRexToken", tokenDeployment.contractAddress);
  const tx = await token.transfer(distributorDeployment.contractAddress, amount);
  await tx.wait();

  const distributorBalance = await token.balanceOf(distributorDeployment.contractAddress);

  console.log("RexClaimDistributor funded");
  console.log(`Distributor: ${distributorDeployment.contractAddress}`);
  console.log(`Amount: ${hre.ethers.formatUnits(amount, tokenDeployment.decimals || 18)} REX`);
  console.log(`Distributor balance: ${hre.ethers.formatUnits(distributorBalance, tokenDeployment.decimals || 18)} REX`);
  console.log(`Tx: ${tx.hash}`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
