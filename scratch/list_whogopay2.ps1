$j = Get-Content "$env:TEMP\whogopay_collection.json" -Raw | ConvertFrom-Json

function GetUrl($req) {
  if ($null -eq $req.url) { return '' }
  if ($req.url -is [string]) { return $req.url }
  return $req.url.raw
}

function Walk($node, $path) {
  foreach ($n in $node) {
    $p = "$path > $($n.name)"
    if ($n.item) { Walk $n.item $p }
    elseif ($n.request) {
      $method = $n.request.method
      $url = GetUrl $n.request
      "$method`t$p`t$url"
    }
  }
}
Walk $j.item ""
