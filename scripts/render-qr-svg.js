const QRCode = require("../rex-wallet/node_modules/qrcode");

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
  const text = String(payload.text || "");
  if (!text) {
    throw new Error("QR text is required.");
  }

  const svg = await QRCode.toString(text, {
    type: "svg",
    width: Number(payload.width || 220),
    margin: Number(payload.margin || 2),
    errorCorrectionLevel: "M",
    color: {
      dark: "#081120",
      light: "#ffffff",
    },
  });
  process.stdout.write(svg);
}

main().catch((error) => {
  process.stderr.write(error && error.message ? error.message : String(error));
  process.exit(1);
});
