const fs = require("fs");
const path = require("path");
const hre = require("hardhat");

const DEFAULT_RESERVE_WALLET = "0x92cA39C926fDf1916c87147c9F4034fB52231F93";
const DEFAULT_CLAIM_FEE_POL = "0.01";

function readTokenDeployment() {
  const fileName = hre.network.name === "amoy"
    ? "polygon-amoy-rex-token.json"
    : `${hre.network.name}-rex-token.json`;
  const filePath = path.join(__dirname, "..", "deployments", fileName);

  if (!fs.existsSync(filePath)) {
    throw new Error(`REX token deployment metadata not found: ${filePath}`);
  }

  return JSON.parse(fs.readFileSync(filePath, "utf8"));
}

async function main() {
  const [deployer] = await hre.ethers.getSigners();

  if (!deployer) {
    throw new Error("No deployer account configured. Set POLYGON_AMOY_PRIVATE_KEY in .env.");
  }

  const network = await hre.ethers.provider.getNetwork();
  const tokenDeployment = readTokenDeployment();
  const reserveWallet = process.env.REX_CLAIM_RESERVE_WALLET || DEFAULT_RESERVE_WALLET;
  const claimSigner = process.env.REX_CLAIM_SIGNER_ADDRESS || deployer.address;
  const claimFee = hre.ethers.parseEther(process.env.REX_CLAIM_FEE_POL || DEFAULT_CLAIM_FEE_POL);

  const Distributor = await hre.ethers.getContractFactory("RexClaimDistributor");
  const distributor = await Distributor.deploy(
    tokenDeployment.contractAddress,
    reserveWallet,
    claimSigner,
    claimFee,
    deployer.address
  );
  await distributor.waitForDeployment();

  const contractAddress = await distributor.getAddress();
  const deployment = {
    contractName: "RexClaimDistributor",
    contractAddress,
    rexTokenAddress: tokenDeployment.contractAddress,
    reserveWallet,
    claimSigner,
    claimFee: claimFee.toString(),
    claimFeeFormatted: hre.ethers.formatEther(claimFee),
    owner: deployer.address,
    network: hre.network.name,
    chainId: Number(network.chainId),
    deployedAt: new Date().toISOString(),
  };

  const deploymentsDir = path.join(__dirname, "..", "deployments");
  fs.mkdirSync(deploymentsDir, { recursive: true });

  const fileName = hre.network.name === "amoy"
    ? "polygon-amoy-rex-claim-distributor.json"
    : `${hre.network.name}-rex-claim-distributor.json`;

  fs.writeFileSync(
    path.join(deploymentsDir, fileName),
    `${JSON.stringify(deployment, null, 2)}\n`,
    "utf8"
  );

  console.log("RexClaimDistributor deployed");
  console.log(`Network: ${deployment.network} (${deployment.chainId})`);
  console.log(`Owner: ${deployment.owner}`);
  console.log(`Contract: ${deployment.contractAddress}`);
  console.log(`REX Token: ${deployment.rexTokenAddress}`);
  console.log(`Reserve wallet: ${deployment.reserveWallet}`);
  console.log(`Claim signer: ${deployment.claimSigner}`);
  console.log(`Claim fee: ${deployment.claimFeeFormatted} POL`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
