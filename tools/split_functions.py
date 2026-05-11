import os
import re

BASE = r"c:\xampp\htdocs\coinrex\includes"
SRC_PATH = os.path.join(BASE, "functions.php")


def extract_functions(lines):
    funcs = []
    outside = []
    i = 0
    n = len(lines)

    while i < n:
        line = lines[i]
        if re.match(r"^\s*function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(", line):
            start = i
            name = re.findall(r"function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(", line)[0]
            brace = 0
            started = False
            while i < n:
                l = lines[i]
                brace += l.count("{")
                if "{" in l:
                    started = True
                brace -= l.count("}")
                if started and brace == 0:
                    end = i
                    i += 1
                    break
                i += 1
            block = "\n".join(lines[start : end + 1])
            funcs.append((name, block))
        else:
            outside.append(line)
            i += 1
    return funcs, outside


def mod_for(name):
    lname = name.lower()
    if lname.startswith("taskhub") or "taskhub" in lname:
        return "taskhub"
    if "boosthub" in lname:
        return "boosthub"
    if lname.startswith("send") or "otp" in lname or "mail" in lname:
        return "email"
    if "remember" in lname or lname in [
        "loginuser",
        "logoutuser",
        "isloggedin",
        "establishauthenticatedsession",
        "restorerememberedsession",
        "touchauthenticateduseractivity",
    ]:
        return "auth"
    if lname.startswith("getuser") or lname.startswith("updateuser") or lname in [
        "registeruser",
        "uploadprofileavatar",
        "isuserprofilecomplete",
        "getcurrentuser",
        "getcurrentuserid",
        "generateusername",
    ]:
        return "user"
    if "ledger" in lname or "claim" in lname or "reward" in lname:
        return "reward_ledger"
    if "referral" in lname:
        return "referrals"
    if "level" in lname or "review" in lname or "project" in lname or "expert" in lname:
        return "levels"
    if (
        "csrf" in lname
        or "sanitize" in lname
        or "security" in lname
        or "disposable" in lname
        or "passwordpolicy" in lname
        or "clientip" in lname
    ):
        return "security"
    if lname in ["redirect", "normalizeemail", "normalizereferralcode", "getemaildomain"]:
        return "helpers"
    return "core"


def main():
    with open(SRC_PATH, "r", encoding="utf-8") as f:
        content = f.read()

    raw = content.replace("<?php", "", 1)
    raw = raw.rsplit("?>", 1)[0]
    lines = raw.splitlines()
    funcs, outside = extract_functions(lines)

    modules = {
        "core": [],
        "auth": [],
        "user": [],
        "email": [],
        "reward_ledger": [],
        "taskhub": [],
        "boosthub": [],
        "levels": [],
        "referrals": [],
        "security": [],
        "helpers": [],
    }

    for name, block in funcs:
        modules[mod_for(name)].append(block)

    func_dir = os.path.join(BASE, "functions")
    os.makedirs(func_dir, exist_ok=True)
    outside_txt = "\n".join(outside).strip("\n")

    for mod, blocks in modules.items():
        path = os.path.join(func_dir, f"{mod}.php")
        with open(path, "w", encoding="utf-8") as f:
            f.write("<?php\n")
            f.write("/** Auto-split from legacy functions.php */\n\n")
            if mod == "core" and outside_txt.strip():
                f.write(outside_txt + "\n\n")
            f.write("\n\n".join(blocks).strip() + "\n")

    legacy = os.path.join(BASE, "functions_legacy_backup.php")
    if not os.path.exists(legacy):
        with open(legacy, "w", encoding="utf-8") as f:
            f.write("<?php\n" + raw + "\n?>")

    loader = """<?php
/**
 * CoinRex Helper Functions Loader
 * Split into modular files for maintainability.
 */

require_once __DIR__ . '/functions/core.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/user.php';
require_once __DIR__ . '/functions/email.php';
require_once __DIR__ . '/functions/reward_ledger.php';
require_once __DIR__ . '/functions/boosthub.php';
require_once __DIR__ . '/functions/taskhub.php';
require_once __DIR__ . '/functions/referrals.php';
require_once __DIR__ . '/functions/levels.php';
require_once __DIR__ . '/functions/auth.php';
?>
"""
    with open(SRC_PATH, "w", encoding="utf-8") as f:
        f.write(loader)

    print("Split complete. Functions:", len(funcs))
    for k, v in modules.items():
        print(k, len(v))


if __name__ == "__main__":
    main()
