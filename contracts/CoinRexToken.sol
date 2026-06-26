// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

contract CoinRexToken is ERC20 {
    uint256 public constant INITIAL_SUPPLY = 1_000_000_000 * 10 ** 18;

    constructor(address initialRecipient) ERC20("CoinRex Token", "REX") {
        require(initialRecipient != address(0), "CoinRexToken: zero recipient");
        _mint(initialRecipient, INITIAL_SUPPLY);
    }
}
