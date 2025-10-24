' Penalty Posting Module - VB.NET Code
' Paste the VB code here from the old system
' This is for reference only to understand the logic and SQL queries


Imports System.ComponentModel
Imports System.Data.SqlClient

Public Class PenaltyForm

    Dim conn As New SqlConnection(My.Settings.ItaxConnString)
    Dim discountmonth As Integer = 0
    Dim discountmonthmode As Boolean = False
    Dim qtradvance As Boolean = False
    Dim qtrpromt As Boolean = False

    Dim disc_annual_advance As Decimal
    Dim disc_annual1 As Decimal
    Dim disc_annual2 As Decimal
    Dim disc_annual3 As Decimal
    Dim disc_qtr_promt As Decimal
    Dim disc_qtr_advance As Decimal

    Dim mon_pen As Decimal
    Private Sub DateTimePicker1_ValueChanged(sender As Object, e As EventArgs) Handles DateTimePicker1.ValueChanged

    End Sub

    Private Sub Label1_Click(sender As Object, e As EventArgs) Handles Label1.Click

    End Sub

    Private Sub RadioButton1_CheckedChanged(sender As Object, e As EventArgs) Handles RadioButton1.CheckedChanged

    End Sub

    Private Sub RadioButton1_Click(sender As Object, e As EventArgs) Handles RadioButton1.Click
        search_b.Enabled = False
        brgycombo.Enabled = True
    End Sub

    Private Sub perproperty_CheckedChanged(sender As Object, e As EventArgs) Handles perproperty.CheckedChanged

    End Sub

    Private Sub perproperty_Click(sender As Object, e As EventArgs) Handles perproperty.Click
        search_b.Enabled = True
        brgycombo.Enabled = False
        PenaltyDS.Properties.Clear()
    End Sub

    Private Sub PenaltyForm_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.T_BARANGAYTableAdapter.Fill(Me.PenaltyDS.T_BARANGAY)
        PropertiesTableAdapter.ClearBeforeFill = False
        brgycombo.SelectedIndex = -1
    End Sub

    Private Sub post_b_Click(sender As Object, e As EventArgs) Handles post_b.Click
        If PenaltyDS.Properties.Rows.Count = 0 Then
            MsgBox("No records found.")
        Else
            Progress.Maximum = PenaltyDS.Properties.Rows.Count - 1
            Progress.Value = 0
            post_b.Enabled = False
            clear_b.Enabled = False
            worker.RunWorkerAsync()
        End If
    End Sub

    Private Sub clear_b_Click(sender As Object, e As EventArgs) Handles clear_b.Click
        Me.PenaltyDS.Properties.Clear()
    End Sub

    Private Sub search_b_Click(sender As Object, e As EventArgs) Handles search_b.Click
        If assessmentsrc Is Nothing Then
        Else
            assessmentsrc.Close()
        End If
        assessmentsrc = New AssessmentSearch
        assessmentsrc.kind = "ALL"
        If assessmentsrc.ShowDialog() = DialogResult.OK Then
            Me.PropertiesTableAdapter.FillByPropertyID(Me.PenaltyDS.Properties, assessmentsrc.prop_id)
        End If

    End Sub

    Private Sub brgycombo_SelectedIndexChanged(sender As Object, e As EventArgs) Handles brgycombo.SelectedIndexChanged
        Me.PenaltyDS.Properties.Clear()
        Me.PropertiesTableAdapter.FillByBarangay(Me.PenaltyDS.Properties, brgycombo.Text)
    End Sub

    Private Sub worker_DoWork(sender As Object, e As System.ComponentModel.DoWorkEventArgs) Handles worker.DoWork
        Dim rowcount As Integer = PenaltyDS.Properties.Rows.Count
        For x As Integer = 0 To rowcount - 1
            worker.ReportProgress(x, "Processing " + (x + 1).ToString + " of " + rowcount.ToString + ". Taxtrans ID: " + PenaltyDS.Properties.Rows(x).Item("TAXTRANS_ID").ToString)

            delete_pendisc(PenaltyDS.Properties.Rows(x).Item("TAXTRANS_ID"), PenaltyDS.Properties.Rows(x).Item("TAXYEAR"))
            loaddiscpen_rates()
            compute_pendisc(PenaltyDS.Properties.Rows(x).Item("TAXTRANS_ID"), PenaltyDS.Properties.Rows(x).Item("TAXYEAR"), PenaltyDS.Properties.Rows(x).Item("LOCAL_TIN"))
        Next
    End Sub

    Private Sub worker_ProgressChanged(sender As Object, e As ProgressChangedEventArgs) Handles worker.ProgressChanged
        Progress.Value = e.ProgressPercentage
        info.Text = e.UserState
    End Sub

    Private Sub worker_RunWorkerCompleted(sender As Object, e As RunWorkerCompletedEventArgs) Handles worker.RunWorkerCompleted
        MsgBox("Penalty posting completed.")
        Progress.Value = 0
        info.Text = "..."
        post_b.Enabled = True
        clear_b.Enabled = True
    End Sub

    Private Sub delete_pendisc(taxtrans_id As Integer, taxyear As Integer)
        Dim com As New SqlCommand("DELETE FROM TPACCOUNT WHERE EARMARK_CT='OPN' AND (EVENTOBJECT_CT='PEN' OR EVENTOBJECT_CT='DED') and TAXTRANS_ID=" + taxtrans_id.ToString + " AND TAXYEAR=" + taxyear.ToString, conn)
        conn.Open()
        com.ExecuteNonQuery()
        conn.Close()
    End Sub

    Private Sub loaddiscpen_rates()
        Me.T_DISCOUNTTableAdapter.Fill(Me.PenaltyDS.T_DISCOUNT)
        Me.T_PENALTYINTERESTPARAMTableAdapter.Fill(Me.PenaltyDS.T_PENALTYINTERESTPARAM)

        With PenaltyDS.T_DISCOUNT
            Dim rw As DataRow()
            rw = .Select("DISCOUNTMONTH=0  AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)

            Try
                disc_annual_advance = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                MsgBox(ex.Message)
                disc_annual_advance = 0
            End Try

            rw = .Select("DISCOUNTMONTH=1 AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)
            Try
                disc_annual1 = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                disc_annual1 = 0
            End Try

            rw = .Select("DISCOUNTMONTH=2 AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)
            Try
                disc_annual2 = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                disc_annual2 = 0
            End Try

            rw = .Select("DISCOUNTMONTH=3 AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)
            Try
                disc_annual3 = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                disc_annual3 = 0
            End Try

            rw = .Select("DISCOUNTMONTH=40 AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)
            Try
                disc_qtr_promt = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                disc_qtr_promt = 0
            End Try

            rw = .Select("DISCOUNTMONTH=41 AND YEARFROM<=" + DateTimePicker1.Value.Year.ToString + " AND YEARTO>=" + DateTimePicker1.Value.Year.ToString)
            Try
                disc_qtr_advance = rw(0).Item("INTERESTRATE")
            Catch ex As Exception
                disc_qtr_advance = 0
            End Try

            Try
                mon_pen = Me.PenaltyDS.T_PENALTYINTERESTPARAM.Rows(0).Item("RATE")
            Catch ex As Exception
                mon_pen = 0
            End Try

        End With

    End Sub

    Private Sub compute_pendisc(taxtrans_id As Integer, taxyear As Integer, localtin As String)

        Me.TPACCOUNTTableAdapter.FillByTaxyeartaxtrans(Me.PenaltyDS.TPACCOUNT, taxtrans_id, taxyear)
        Dim n As DataRow
        Dim en As Integer = PenaltyDS.TPACCOUNT.Rows.Count - 1
        Dim dbset As New DataSet
        Dim adap As SqlDataAdapter
        Dim credits As Decimal
        For x As Integer = 0 To en

            With PenaltyDS.TPACCOUNT.Rows(x)
                dbset.Clear()
                adap = New SqlDataAdapter("select sum(debitamount)/2 as credits from TPAccount WHERE (EVENTOBJECT_CT='TCR' or EVENTOBJECT_CT='TDF') AND EARMARK_CT='OPN' AND TAXYEAR=" + .Item("TAXYEAR").ToString + " AND TAXPERIOD_CT=" + .Item("TAXPERIOD_CT").ToString + " AND PROP_ID=" + .Item("PROP_ID").ToString, conn)
                'MsgBox(.Item("PROP_ID").ToString)
                adap.Fill(dbset, "a")
                If dbset.Tables("a").Rows.Count = 0 Then
                    credits = 0
                Else
                    Try
                        credits = dbset.Tables("a").Rows(0).Item(0)
                    Catch ex As Exception
                        credits = 0
                    End Try

                End If
                If .Item("EVENTOBJECT_CT") = "TDF" Or .Item("EVENTOBJECT_CT") = "TCR" Then
                Else
                    n = PenaltyDS.TPACCOUNT.NewRow
                    n("TAXTRANS_ID") = .Item("TAXTRANS_ID")
                    n("REFPOSTINGID") = .Item("POSTING_ID")
                    n("LOCAL_TIN") = localtin
                    n("PROP_ID") = .Item("PROP_ID")
                    n("TAXYEAR") = .Item("TAXYEAR")
                    n("ITAXTYPE_CT") = .Item("ITAXTYPE_CT")
                    n("MUNICIPAL_ID") = currentmunicipalID
                    n("EVENTOBJECT_CT") = event_object(Val(.Item("TAXPERIOD_CT")), Val(.Item("TAXYEAR")))
                    n("CASETYPE_CT") = n("EVENTOBJECT_CT")
                    n("DEBITAMOUNT") = Round(ded_penvalue(Val(.Item("TAXPERIOD_CT")), Val(.Item("TAXYEAR")), n("EVENTOBJECT_CT"), .Item("DEBITAMOUNT") + credits), 2)
                    n("VALUEDATE") = Now.Date
                    n("CREDITAMOUNT") = 0
                    n("EARMARK_CT") = "OPN"
                    n("TAXPERIOD_CT") = .Item("TAXPERIOD_CT")
                    n("USERID") = currentuser
                    n("TRANSDATE") = CType(Now.Date, DateTime)
                    If .Item("EVENTOBJECT_CT") = "PBL" Then
                        n("BOOKINGREFERENCE") = "PBL2"
                    End If
                    n("CANCELLED_BV") = False

                    PenaltyDS.TPACCOUNT.Rows.Add(n)

                End If
            End With

        Next

        Validate()
        TPBS.EndEdit()
        TPACCOUNTTableAdapter.Update(PenaltyDS.TPACCOUNT)

    End Sub

    Private Function event_object(taxperiod As Integer, taxyear As Integer) As String
        discountmonth = 0
        qtradvance = False
        qtrpromt = False

        discountmonthmode = False
        If DateTimePicker1.Value.Year > taxyear Then
            'MsgBox("return penalty")
            Return "PEN"
        ElseIf DateTimePicker1.Value.Year < taxyear Then

            If taxperiod = 41 Then
                qtradvance = True
            End If
            Return "DED"

        End If

        If taxperiod = 99 Then 'Annual
            If DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month >= 4 Then
                Return "PEN"
            ElseIf DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month <= 3 Then
                discountmonth = DateTimePicker1.Value.Month
                discountmonthmode = True
                Return "DED"
            End If
        ElseIf taxperiod = 21 Then 'First Semi-Annual
            If DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month >= 7 Then
                Return "PEN"
            ElseIf DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month <= 6 Then
                If DateTimePicker1.Value.Month <= 3 Then
                    discountmonth = DateTimePicker1.Value.Month
                    discountmonthmode = True
                End If
                Return "DED"
            End If
        ElseIf taxperiod = 22 Then 'Second Semi-Annual
            Return "DED"
        ElseIf taxperiod = 41 Then 'First Quarter
            If DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month >= 4 Then
                Return "PEN"
            ElseIf DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month <= 3 Then
                If DateTimePicker1.Value.Month = 1 Then
                    qtrpromt = True
                End If
                Return "DED"
            End If
        ElseIf taxperiod = 42 Then 'Second Quarter
            If DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month >= 7 Then
                Return "PEN"
            ElseIf DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month <= 6 Then
                If DateTimePicker1.Value.Month = 4 Then
                    qtrpromt = True
                ElseIf DateTimePicker1.Value.Month < 4 Then
                    qtradvance = True
                End If
                Return "DED"
            End If
        ElseIf taxperiod = 43 Then 'Third Quarter
            If DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month >= 10 Then
                Return "PEN"
            ElseIf DateTimePicker1.Value.Year = taxyear And DateTimePicker1.Value.Month <= 9 Then
                If DateTimePicker1.Value.Month = 7 Then
                    qtrpromt = True
                ElseIf DateTimePicker1.Value.Month < 7 Then
                    qtradvance = True
                End If
                Return "DED"
            End If
        ElseIf taxperiod = 44 Then 'Fourth Quarter
            If DateTimePicker1.Value.Month = 10 Then
                qtrpromt = True
            ElseIf DateTimePicker1.Value.Month < 10 Then
                qtradvance = True
            End If
            Return "DED"
        End If

        Return "INV"

    End Function

    Private Function ded_penvalue(taxperiod As Integer, taxyear As Integer, event_ct As String, taxable As Decimal) As Decimal

        If event_ct = "DED" Then
            If taxperiod = 99 Then
                If discountmonthmode = True Then
                    If discountmonth = 1 Then
                        Return -(taxable * disc_annual1)
                    ElseIf discountmonth = 2 Then
                        Return -(taxable * disc_annual2)
                    ElseIf discountmonth = 3 Then
                        Return -(taxable * disc_annual3)
                    End If
                Else
                    Return -(taxable * disc_annual_advance)
                End If
            ElseIf taxperiod = 21 Or taxperiod = 22 Then
                Return -(taxable * disc_qtr_advance)
            ElseIf taxperiod = 41 Or taxperiod = 42 Or taxperiod = 43 Or taxperiod = 44 Then
                Return -(taxable * disc_qtr_advance)
            End If
        ElseIf event_ct = "PEN" Then
            If taxperiod = 99 Then
                Dim d As Integer = distance(1, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 21 Then
                Dim d As Integer = distance(1, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 22 Then
                Dim d As Integer = distance(7, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 41 Then
                Dim d As Integer = distance(1, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 42 Then
                Dim d As Integer = distance(4, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 43 Then
                Dim d As Integer = distance(7, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            ElseIf taxperiod = 44 Then
                Dim d As Integer = distance(10, taxyear, DateTimePicker1.Value.Month, DateTimePicker1.Value.Year)
                If taxyear <= 1991 Then
                    d = 12
                End If
                If d <= 36 Then
                    Return (taxable * mon_pen) * d
                Else
                    Return (taxable * mon_pen) * 36
                End If
            End If
        End If

        Return 0
    End Function

    Private Function distance(mon1 As Integer, year1 As Integer, mon2 As Integer, year2 As Integer) As Integer
        Dim counter As Integer = 1
Back:
        If mon1 = 12 Then
            mon1 = 1
            year1 += 1
        Else
            mon1 += 1
        End If
        counter = counter + 1

        If mon1 = mon2 And year1 = year2 Then
        Else
            GoTo Back
        End If
        Return counter
    End Function

End Class