# Week 16: sample CPU and memory of the server processes every 2 seconds while a load run is going.
#   powershell -File perf\sample-resources.ps1 -Seconds 200 -Out evidence\week16\P1\resources.csv
# CPU % is of the whole machine (all logical cores = 100 %), from the change in each process's CPU time.
param([int]$Seconds = 200, [string]$Out = 'resources.csv', [int]$Interval = 2)

$names = 'php-cgi', 'php', 'mysqld', 'nginx', 'node'
$cores = [Environment]::ProcessorCount
"time_utc,process,count,cpu_pct,working_set_mb,machine_cpu_pct,machine_free_mb" | Out-File -Encoding utf8 $Out

function Snapshot {
    $h = @{}
    foreach ($p in Get-Process -Name $names -ErrorAction SilentlyContinue) {
        if (-not $h.ContainsKey($p.ProcessName)) { $h[$p.ProcessName] = @{ cpu = 0.0; ws = 0; n = 0 } }
        $h[$p.ProcessName].cpu += $p.TotalProcessorTime.TotalSeconds
        $h[$p.ProcessName].ws += $p.WorkingSet64
        $h[$p.ProcessName].n += 1
    }
    return $h
}

$prev = Snapshot
$end = (Get-Date).AddSeconds($Seconds)
while ((Get-Date) -lt $end) {
    Start-Sleep -Seconds $Interval
    $cur = Snapshot
    $os = Get-CimInstance Win32_OperatingSystem
    $machine = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
    $t = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    foreach ($name in $cur.Keys) {
        $before = if ($prev.ContainsKey($name)) { $prev[$name].cpu } else { 0 }
        $cpu = [math]::Max(0, ($cur[$name].cpu - $before) / $Interval / $cores * 100)
        "{0},{1},{2},{3:N1},{4:N0},{5},{6:N0}" -f $t, $name, $cur[$name].n, $cpu, ($cur[$name].ws / 1MB), $machine, ($os.FreePhysicalMemory / 1KB) | Out-File -Append -Encoding utf8 $Out
    }
    $prev = $cur
}
