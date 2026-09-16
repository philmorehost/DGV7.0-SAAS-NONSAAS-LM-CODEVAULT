$j = Get-Content "$env:TEMP\whogopay_collection.json" -Raw | ConvertFrom-Json

function Walk($node) {
  foreach ($n in $node) {
    if ($n.item) { Walk $n.item }
    elseif ($n.request) {
      if ($n.name -like '*Verify Transaction*' -or $n.name -like '*Fetch All Transactions*' -or $n.name -like '*SoftPos Checkout*') {
        "===== $($n.name) ====="
        $n | ConvertTo-Json -Depth 40
        ""
      }
    }
  }
}
Walk $j.item
