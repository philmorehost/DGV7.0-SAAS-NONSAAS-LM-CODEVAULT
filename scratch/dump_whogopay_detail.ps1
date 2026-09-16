$j = Get-Content "$env:TEMP\whogopay_collection.json" -Raw | ConvertFrom-Json

$want = @('Verify Transaction','SoftPos Checkout','Bank Transfer.','Card Funding','Fetch Users Balance')

function Walk($node) {
  foreach ($n in $node) {
    if ($n.item) { Walk $n.item }
    elseif ($n.request) {
      foreach ($w in $want) {
        if ($n.name -like "*$w*") {
          "===== ENDPOINT: $($n.name) ====="
          "--- REQUEST ---"
          $req = $n.request
          "METHOD: $($req.method)"
          "URL: $($req.url.raw)"
          "AUTH: $($req.auth.type)"
          if ($req.auth.bearer) { "AUTH TOKEN: $($req.auth.bearer.token)" }
          "HEADERS:"
          foreach ($h in $req.header) { "  $($h.key): $($h.value)" }
          if ($req.body) {
            "BODY ($($req.body.mode)):"
            $req.body.raw
          }
          "--- RESPONSES ---"
          foreach ($r in $n.response) {
            "  [$($r.code) $($r.status)]"
            $r.body
          }
          ""
        }
      }
    }
  }
}
Walk $j.item
