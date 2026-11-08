#!/usr/bin/env bash
# Menyalin library pihak ketiga ke assets/vendor supaya aplikasi tidak bergantung pada CDN luar.
# Jalankan dari folder aplikasi:   bash alat/ambil-vendor.sh
# Setelah itu upload folder assets/vendor ke public_html/assets/vendor di Hostinger
# (atau pakai ZIP tb-vendor.zip yang dibuat skrip ini).
set -euo pipefail
cd "$(dirname "$0")/.."
V=assets/vendor
BS=5.3.3; BI=1.11.3; LF=1.9.4
CDN=https://cdn.jsdelivr.net/npm

ambil() { # url tujuan teks-wajib(opsional)
  mkdir -p "$(dirname "$2")"
  curl -fsSL --retry 3 "$1" -o "$2.tmp"
  if [ ! -s "$2.tmp" ]; then echo "GAGAL (kosong): $1"; exit 1; fi
  if [ -n "${3:-}" ] && ! LC_ALL=C grep -q "$3" "$2.tmp"; then echo "GAGAL (isi tidak cocok): $1"; exit 1; fi
  mv "$2.tmp" "$2"; echo "  ok  $2"
}

echo "Mengunduh library ke $V ..."
ambil "$CDN/bootstrap@$BS/dist/css/bootstrap.min.css"        "$V/bootstrap/bootstrap.min.css"        "Bootstrap  v$BS"
ambil "$CDN/bootstrap@$BS/dist/js/bootstrap.bundle.min.js"   "$V/bootstrap/bootstrap.bundle.min.js"  "Bootstrap v$BS"
ambil "$CDN/bootstrap-icons@$BI/font/bootstrap-icons.min.css" "$V/bootstrap-icons/bootstrap-icons.min.css" "bootstrap-icons"
ambil "$CDN/bootstrap-icons@$BI/font/fonts/bootstrap-icons.woff2" "$V/bootstrap-icons/fonts/bootstrap-icons.woff2" "wOF2"
ambil "$CDN/bootstrap-icons@$BI/font/fonts/bootstrap-icons.woff"  "$V/bootstrap-icons/fonts/bootstrap-icons.woff"  "wOFF"
ambil "$CDN/leaflet@$LF/dist/leaflet.css" "$V/leaflet/leaflet.css" "leaflet"
ambil "$CDN/leaflet@$LF/dist/leaflet.js"  "$V/leaflet/leaflet.js"  "$LF"
for g in layers.png layers-2x.png marker-icon.png marker-icon-2x.png marker-shadow.png; do
  ambil "$CDN/leaflet@$LF/dist/images/$g" "$V/leaflet/images/$g" "PNG"
done

# Font Plus Jakarta Sans (dari paket @fontsource, lisensi OFL)
CSS="$V/fonts/fonts.css"; mkdir -p "$V/fonts"; : > "$CSS.tmp"
for sub in latin latin-ext; do
  for w in 400 500 600 700 800; do
    f="plus-jakarta-sans-$sub-$w-normal.woff2"
    ambil "$CDN/@fontsource/plus-jakarta-sans@5/files/$f" "$V/fonts/$f" "wOF2"
    if [ "$sub" = latin ]; then
      R="U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD"
    else
      R="U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF"
    fi
    printf "@font-face{font-family:'Plus Jakarta Sans';font-style:normal;font-weight:%s;font-display:swap;src:url(%s) format('woff2');unicode-range:%s}\n" "$w" "$f" "$R" >> "$CSS.tmp"
  done
done
mv "$CSS.tmp" "$CSS"; echo "  ok  $CSS"

# ZIP siap upload ke Hostinger (ekstrak di public_html)
rm -f tb-vendor.zip
if command -v zip >/dev/null; then zip -rq tb-vendor.zip "$V" && echo "Selesai. File tb-vendor.zip siap diupload ke public_html lalu di-Extract."; fi
