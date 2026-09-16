$j = Get-Content "$env:TEMP\whogopay_collection.json" -Raw | ConvertFrom-Json

# Recursively find items whose name matches any of the given substrings, dump their full JSON
$want = @('Verify Transaction','Card Funding','Bank Transfer','Checkout','SoftPos','Balance','All Transactions','Webhook','Virtual Account Customer','Exchange')

function Walk($node) {
  foreach ($n in $node) {
    if ($n.item) { Walk $n.item }
    elseif ($n.request) {
      foreach ($w in $want) {
        if ($n.name -like "*$w*") {
          "===== $($n.name) ====="
          $n | ConvertTo-Json -Depth 30
          ""
        }
      }
    }
  }
}
Walk $j.item
