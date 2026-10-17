#!/bin/bash
# Smoke test: login sebagai setiap peran demo lalu GET semua halaman utama.
# Melaporkan kode HTTP per halaman (200 = boleh, 403 = ditolak sesuai matriks, 500 = BUG).
#
# Pemakaian (server dev berjalan & database berisi data demo):
#   php artisan serve --port=8123
#   bash tests/e2e/smoke-pages.sh [http://127.0.0.1:8123]
BASE=${1:-http://127.0.0.1:8123}
PAGES="/dashboard /profile /master/accounts /master/accounts/create /master/periods /master/templates
/transactions /transactions/create/penerimaan-kas /transactions/create/pengeluaran-kas
/transactions/create/invoice-penjualan /transactions/create/invoice-pembelian /transactions/1 /transactions/7
/approvals /approvals/history /journals /journals/1 /journals/create /journals/reversals /journals/batch
/gl/ledger /gl/summary /gl/trial-balance /settings/users /settings/users/create /settings/audit-logs
/settings/general /transactions/export"
FAIL=0

for ROLE in admin staff supervisor manager akuntan auditor; do
  JAR=$(mktemp)
  TOKEN=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -c "$JAR" -b "$JAR" -o /dev/null -X POST -d "_token=$TOKEN&email=$ROLE@finance.test&password=password" "$BASE/login"
  LINE="$ROLE:"
  for P in $PAGES; do
    CODE=$(curl -s -c "$JAR" -b "$JAR" -o /dev/null -w "%{http_code}" "$BASE$P")
    [ "$CODE" = "500" ] && FAIL=1
    LINE="$LINE $P=$CODE"
  done
  echo "$LINE"
  rm -f "$JAR"
done

[ $FAIL = 1 ] && { echo "ADA HALAMAN ERROR 500"; exit 1; }
echo "OK — tidak ada error 500"
