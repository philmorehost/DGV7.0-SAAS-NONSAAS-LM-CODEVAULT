$j = Get-Content "$env:TEMP\whogopay_collection.json" -Raw | ConvertFrom-Json

function Walk($node, $path) {
  foreach ($n in $node) {
    $p = "$path > $($n.name)"
    if ($n.item) {
      Walk $n.item $p
    } elseif ($n.request) {
      $method = $n.request.method
      $url = $n.request.url.raw
      [PSCustomObject]@{ Method=$method; Path=$p; Url=$url }
    }
  }
}

Walk $j.item "" | Format-List
