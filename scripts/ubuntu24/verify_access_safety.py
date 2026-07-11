from __future__ import annotations

from pathlib import Path
import re
import sys


ROOT = Path(__file__).resolve().parent
FORBIDDEN = {
    r"^[ \t]*(?:sudo[ \t]+)?(?:/usr/sbin/)?(ufw|iptables|ip6tables|nft)\b": "Firewall-Befehl",
    r"^[ \t]*(?:sudo[ \t]+)?(?:/usr/sbin/)?(reboot|shutdown|poweroff|halt)\b": "Neustart- oder Ausschaltbefehl",
    r"^[ \t]*(?:sudo[ \t]+)?apt(?:-get)?\s+(?:dist-|full-)?upgrade\b": "Paket-Upgrade",
    r"^[ \t]*(?:sudo[ \t]+)?a2dissite\b": "Deaktivieren bestehender Apache-Sites",
    r"^[ \t]*(?:sudo[ \t]+)?systemctl\s+(?:stop|restart|disable|mask)\s+ssh": "Eingriff in SSH-Dienst",
    r"^[ \t]*(?:sudo[ \t]+)?(?:service|invoke-rc\.d)\s+ssh\b": "Eingriff in SSH-Dienst",
    r"^[ \t]*(?:sudo[ \t]+)?(?:cp|mv|install|chmod|chown|touch|mkdir|tee|sed)\b[^\n]*/etc/ssh": "Schreibzugriff auf SSH-Konfiguration",
    r"^[^#\n]*>[^\n]*/etc/ssh": "Umleitung in SSH-Konfiguration",
    r"/etc/(?:netplan|network|NetworkManager|systemd/network|resolv\.conf)": "Netzwerkkonfiguration",
    r"^[ \t]*(?:sudo[ \t]+)?rm\s+-(?:[A-Za-z]*r[A-Za-z]*f|[A-Za-z]*f[A-Za-z]*r)\b": "rekursives erzwungenes Loeschen",
}


def main() -> int:
    findings: list[str] = []
    for path in sorted(ROOT.glob("*.sh")):
        text = path.read_text(encoding="utf-8")
        for pattern, description in FORBIDDEN.items():
            for match in re.finditer(pattern, text, flags=re.IGNORECASE | re.MULTILINE):
                line = text.count("\n", 0, match.start()) + 1
                findings.append(f"{path.name}:{line}: {description}")
    if findings:
        print("\n".join(findings), file=sys.stderr)
        return 1
    print("Zugriffsschutz-Pruefung erfolgreich.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
