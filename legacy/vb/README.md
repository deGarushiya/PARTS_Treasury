# Legacy VB Code Drop

Place legacy Visual Basic (VB6/VB.NET) source here so we can analyze the flow and port logic.

## How to use

1. Open `legacy/vb/TaxpayerDebit_dump.txt` and paste the entire contents of the Taxpayer Debit form/module (all 1900+ lines is fine).
2. If the code is split across multiple files in your project, either:
   - Paste everything into `TaxpayerDebit_dump.txt`, or
   - Create additional files alongside it (e.g., `TaxpayerDebit_Events.txt`, `TaxpayerDebit_DataAccess.txt`, `TaxpayerDebit_ComputePen.txt`, `TaxpayerDebit_Credits.txt`).

## What is most useful

- Form load/init (grid population)
- Property grid selection handlers
- Dues grid population (SQL/queries)
- Button handlers: Unselect All, Compute PEN, Remove PEN, Tax Credit, Remove Credits, Bi-Annual, Quarterly, Undo Division, Print Tax Bill
- Penalty/discount computation
- Credits logic and sources
- Any constants/parameters used by those calculations

Once pasted, tell me and I will read and map the behavior into our Laravel/React implementation.


