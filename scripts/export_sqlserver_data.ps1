# PowerShell script to export data from SQL Server 2012
# Run this script on the machine with SQL Server access

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerName,
    
    [Parameter(Mandatory=$true)]
    [string]$DatabaseName,
    
    [Parameter(Mandatory=$false)]
    [string]$Username,
    
    [Parameter(Mandatory=$false)]
    [string]$Password,
    
    [Parameter(Mandatory=$false)]
    [string]$OutputPath = ".\exports"
)

# Create output directory if it doesn't exist
if (!(Test-Path $OutputPath)) {
    New-Item -ItemType Directory -Path $OutputPath -Force
}

# Build connection string
if ($Username -and $Password) {
    $connectionString = "Server=$ServerName;Database=$DatabaseName;User Id=$Username;Password=$Password;"
} else {
    $connectionString = "Server=$ServerName;Database=$DatabaseName;Integrated Security=true;"
}

Write-Host "Connecting to SQL Server: $ServerName" -ForegroundColor Green
Write-Host "Database: $DatabaseName" -ForegroundColor Green
Write-Host "Output Path: $OutputPath" -ForegroundColor Green

# Define tables to export
$tables = @(
    "TAXPAYER",
    "PROPERTY", 
    "RPTASSESSMENT",
    "POSTINGJOURNAL",
    "PAYMENT",
    "PAYMENTDETAIL",
    "PAYMENTCHEQUE",
    "T_BARANGAY",
    "T_PROPERTYKIND",
    "TPACCOUNT",
    "PROPERTYOWNER",
    "RPTCANCELLED"
)

# Function to export table to CSV
function Export-TableToCSV {
    param(
        [string]$TableName,
        [string]$ConnectionString,
        [string]$OutputPath
    )
    
    try {
        Write-Host "Exporting table: $TableName" -ForegroundColor Yellow
        
        # Create SQL query
        $query = "SELECT * FROM [$TableName]"
        
        # Execute query and export to CSV
        $csvPath = Join-Path $OutputPath "$TableName.csv"
        
        # Use sqlcmd to export data
        $sqlcmdArgs = @(
            "-S", $ServerName,
            "-d", $DatabaseName,
            "-Q", $query,
            "-o", $csvPath,
            "-s", ",",  # Comma separator
            "-W"        # Remove trailing spaces
        )
        
        if ($Username -and $Password) {
            $sqlcmdArgs += @("-U", $Username, "-P", $Password)
        } else {
            $sqlcmdArgs += @("-E")  # Use Windows Authentication
        }
        
        & sqlcmd @sqlcmdArgs
        
        if (Test-Path $csvPath) {
            Write-Host "✓ Successfully exported $TableName to $csvPath" -ForegroundColor Green
        } else {
            Write-Host "✗ Failed to export $TableName" -ForegroundColor Red
        }
        
    } catch {
        Write-Host "✗ Error exporting $TableName : $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Export each table
foreach ($table in $tables) {
    Export-TableToCSV -TableName $table -ConnectionString $connectionString -OutputPath $OutputPath
}

Write-Host "`nExport completed!" -ForegroundColor Green
Write-Host "Files exported to: $OutputPath" -ForegroundColor Green

# Create a summary file
$summaryPath = Join-Path $OutputPath "export_summary.txt"
$summary = @"
SQL Server Data Export Summary
=============================
Server: $ServerName
Database: $DatabaseName
Export Date: $(Get-Date)
Output Path: $OutputPath

Tables Exported:
"@

foreach ($table in $tables) {
    $csvPath = Join-Path $OutputPath "$table.csv"
    if (Test-Path $csvPath) {
        $fileSize = (Get-Item $csvPath).Length
        $summary += "`n✓ $table.csv ($fileSize bytes)"
    } else {
        $summary += "`n✗ $table.csv (failed)"
    }
}

$summary | Out-File -FilePath $summaryPath -Encoding UTF8
Write-Host "`nSummary saved to: $summaryPath" -ForegroundColor Cyan
