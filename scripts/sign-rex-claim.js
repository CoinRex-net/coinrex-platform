const { Wallet } = require("ethers");

function readStdin() {
  return new Promise((resolve, reject) => {
    let input = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      input += chunk;
    });
    process.stdin.on("end", () => resolve(input));
    process.stdin.on("error", reject);
  });
}

async function main() {
  const payload = JSON.parse(await readStdin());
  const privateKey = String(process.env.REX_CLAIM_SIGNER_PRIVATE_KEY || process.env.POLYGON_AMOY_PRIVATE_KEY || "").trim();

  if (!privateKey) {
    throw new Error("REX_CLAIM_SIGNER_PRIVATE_KEY is not configured.");
  }

  const wallet = new Wallet(privateKey.startsWith("0x") ? privateKey : `0x${privateKey}`);
  const signature = await wallet.signTypedData(
    {
      name: "CoinRex Claim Distributor",
      version: "1",
      chainId: Number(payload.chainId),
      verifyingContract: payload.contractAddress,
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
      claimant: payload.claimant,
      snapshotId: String(payload.snapshotId),
      amount: String(payload.amount),
      deadline: String(payload.deadline),
    }
  );

  process.stdout.write(JSON.stringify({
    signerAddress: wallet.address,
    signature,
  }));
}

main().catch((error) => {
  process.stderr.write(error && error.message ? error.message : String(error));
  process.exit(1);
});
