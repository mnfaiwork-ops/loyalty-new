#!/usr/bin/env bash
#
# Cek apakah PHP yang melayani web di server ini sudah aman dari PHP2Shell
# (CVE-2026-109618 dan CVE-2026-109619).
#
#   bash scripts/check-php2shell.sh                         # cari semua php-fpm / php-cgi
#   bash scripts/check-php2shell.sh /usr/sbin/php-fpm8.5    # cek satu binary
#
# Aman kalau versi PHP >= 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35, atau kalau paket
# distro (Debian/Ubuntu/RHEL) sudah memuat patch kedua CVE di changelog-nya.
# Distro sering menambal tanpa menaikkan nomor versi PHP, jadi changelog ikut dicek.
#
# Exit: 0 semua aman, 1 ada yang rentan, 2 tidak ada PHP web yang ditemukan.
set -uo pipefail

CVES=(CVE-2026-109618 CVE-2026-109619)

fixed_version() {
  case "$1" in
    8.5) echo 8.5.12 ;;
    8.4) echo 8.4.27 ;;
    8.3) echo 8.3.36 ;;
    8.2) echo 8.2.35 ;;
    *)   echo "" ;;   # 8.1 ke bawah: tidak ada rilis perbaikan dari PHP
  esac
}

version_ge() {
  [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -1)" = "$2" ]
}

# Isi changelog paket pemilik binary, kosong kalau bukan paket dpkg/rpm.
package_changelog() {
  local bin pkg
  bin="$(readlink -f "$1")"
  if command -v dpkg >/dev/null 2>&1 && pkg="$(dpkg -S "$bin" 2>/dev/null | cut -d: -f1 | head -1)" && [ -n "$pkg" ]; then
    echo "  paket: $pkg $(dpkg-query -W -f='${Version}' "$pkg" 2>/dev/null)" >&2
    zcat "/usr/share/doc/$pkg/changelog.Debian.gz" 2>/dev/null
  elif command -v rpm >/dev/null 2>&1 && pkg="$(rpm -qf "$bin" 2>/dev/null)"; then
    echo "  paket: $pkg" >&2
    rpm -q --changelog "$pkg" 2>/dev/null
  fi
}

check_ini() {
  local bin="$1" info
  info="$("$bin" -i 2>/dev/null)" || return
  grep -E '^(session\.upload_progress\.enabled|fatal_error_backtraces) =>' <<<"$info" | sed 's/^/  ini: /'
}

check_bin() {
  local bin="$1" ver branch fixed log missing=() cve
  ver="$("$bin" -v 2>/dev/null | head -1 | awk '{print $2}')"
  ver="${ver%%[-+~]*}"
  if [ -z "$ver" ]; then
    echo "?? $bin: versi tidak terbaca, cek manual"
    return 1
  fi
  branch="${ver%.*}"
  fixed="$(fixed_version "$branch")"
  echo "== $bin (PHP $ver)"

  if [ -n "$fixed" ] && version_ge "$ver" "$fixed"; then
    echo "  AMAN: versi >= $fixed"
    return 0
  fi

  log="$(package_changelog "$bin")"
  for cve in "${CVES[@]}"; do
    grep -q "$cve" <<<"$log" || missing+=("$cve")
  done
  if [ -n "$log" ] && [ ${#missing[@]} -eq 0 ]; then
    echo "  AMAN: paket distro sudah memuat patch ${CVES[*]}"
    return 0
  fi

  if [ -n "$fixed" ]; then
    echo "  RENTAN: perlu PHP >= $fixed atau paket distro dengan patch ${missing[*]}"
  else
    echo "  RENTAN: PHP $branch sudah EOL, PHP tidak merilis perbaikan. Naik ke 8.2+ atau pakai backport distro/LTS."
  fi
  check_ini "$bin"
  return 1
}

if [ $# -gt 0 ]; then
  bins=("$@")
else
  mapfile -t bins < <(ls /usr/sbin/php-fpm* /usr/local/sbin/php-fpm* /usr/bin/php-cgi* /usr/local/bin/php-cgi* 2>/dev/null | sort -u)
fi

mods="$(ls /usr/lib/apache2/modules/libphp*.so /etc/httpd/modules/libphp*.so 2>/dev/null)"
if [ -n "$mods" ]; then
  echo "Apache mod_php terpasang, ikut rentan kalau belum di-update:"
  echo "$mods" | sed 's/^/  /'
fi

if [ ${#bins[@]} -eq 0 ]; then
  echo "Tidak ada php-fpm / php-cgi. Kalau PHP jalan di Docker, cek di dalam container:"
  echo "  docker exec <container> php -v"
  exit 2
fi

status=0
for bin in "${bins[@]}"; do
  check_bin "$bin" || status=1
done

echo
if [ $status -eq 0 ]; then
  echo "Hasil: AMAN"
else
  echo "Hasil: RENTAN. Ikuti docs/deploy/2026-10-22/PATCH-PHP2SHELL.md"
fi
exit $status
