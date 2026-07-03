const { verifyMessage, getAddress } = require("ethers");

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
  const message = String(payload.message || "");
  const signature = String(payload.signature || "");
  const expectedAddress = String(payload.wallet_address || payload.address || "").trim();

  if (!message || !signature || !expectedAddress) {
    throw new Error("message, signature, and wallet_address are required.");
  }

  const recovered = getAddress(verifyMessage(message, signature));
  const expected = getAddress(expectedAddress);

  process.stdout.write(JSON.stringify({
    valid: recovered.toLowerCase() === expected.toLowerCase(),
    recoveredAddress: recovered,
    expectedAddress: expected,
  }));
}

main().catch((error) => {
  process.stderr.write(error && error.message ? error.message : String(error));
  process.exit(1);
});
