require("dotenv").config();
require("@nomicfoundation/hardhat-ethers");

const { subtask } = require("hardhat/config");
const {
  TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD,
} = require("hardhat/builtin-tasks/task-names");

const amoyRpcUrl = process.env.POLYGON_AMOY_RPC_URL || "";
const deployerPrivateKey = process.env.POLYGON_AMOY_PRIVATE_KEY || "";

subtask(TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD).setAction(
  async ({ solcVersion }) => {
    const solc = require("solc");
    const longVersion = solc.version();

    if (!longVersion.startsWith(`${solcVersion}+`)) {
      throw new Error(
        `Local solc version ${longVersion} does not match requested ${solcVersion}.`
      );
    }

    return {
      compilerPath: require.resolve("solc/soljson.js"),
      isSolcJs: true,
      version: solcVersion,
      longVersion,
    };
  }
);

module.exports = {
  solidity: {
    version: "0.8.24",
    settings: {
      optimizer: {
        enabled: true,
        runs: 200,
      },
    },
  },
  networks: {
    hardhat: {
      chainId: 31337,
    },
    amoy: {
      url: amoyRpcUrl || "https://rpc-amoy.polygon.technology",
      chainId: 80002,
      accounts: deployerPrivateKey ? [deployerPrivateKey] : [],
    },
  },
  paths: {
    sources: "./contracts",
    tests: "./test",
    cache: "./cache",
    artifacts: "./artifacts",
  },
};
