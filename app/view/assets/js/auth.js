const formatMoney = value => {
    if (value < 1e3) {
        return "";
    }
    const keys = [
        [1e3, 1e6, "k"],
        [1e6, 1e9, "m"],
        [1e9, 1e12, "b"],
        [1e12, 1e15, "t"],
        [1e15, 1e18, "qa"],
        [1e18, 1e21, "qi"],
        [1e21, 1e24, "sx"],
        [1e24, 1e27, "sp"],
        [1e27, 1e30, "oc"],
        [1e30, 1e33, "no"],
        [1e33, 1e36, "de"],
    ];
    for (const key of keys) {
        if (value > key[0] && value < key[1]) {
            return `$${(value / key[0]).toFixed(1).toLocaleString()}${key[2]}`;
        }
    }
};
