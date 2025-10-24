import React, { useState, useEffect } from "react";
import "./PaymentPostingPage.css";
import OwnerSearchModal from "../../components/OwnerSearchModal/OwnerSearchModal";
import GetTaxDueModal from "./GetTaxDueModal";
import { paymentAPI, taxDueAPI } from "../../services/api";


const PaymentPostingPage = () => {
  const [selectedRowId, setSelectedRowId] = useState(null);
  const [showOwnerModal, setShowOwnerModal] = useState(false);
  const [isNewEnabled, setIsNewEnabled] = useState(true);
  const [isFindTaxpayerEnabled, setIsFindTaxpayerEnabled] = useState(false);
  const [isVoidPaymentEnabled, setIsVoidPaymentEnabled] = useState(false);
  const [isDeletePaymentEnabled, setIsDeletePaymentEnabled] = useState(false);
  const [isGetTaxDueEnabled, setIsGetTaxDueEnabled] = useState(false);
  const [isReceiptEnabled, setIsReceiptEnabled] = useState(false);
  const [formDirty, setFormDirty] = useState(false);
  const [journalData, setJournalData] = useState([]);
  const [detailsData, setDetailsData] = useState([]);
  const [showGetTaxDue, setShowGetTaxDue] = useState(false);

  const [paymentData, setPaymentData] = useState({
    LOCAL_TIN: "",
    AFTYPE: "",
    taxpayerName: "",
    receiptNo: "",
    paidBy: "",
    remarks: "",
    paymentDate: "",
  });
  
  /** = HANDLERS = */

  const handleOpenGetTaxDue = () => {
    setShowGetTaxDue(true);
  };

  const handleCloseGetTaxDue = () => {
    setShowGetTaxDue(false);
  };

  const handleJournalClick = (row) => {
    setSelectedRowId(row.id);
    setPaymentData({
      LOCAL_TIN: row.localTin || "",
      AFTYPE: row.afType || "",
      taxpayerName: row.taxpayerName || "",
      receiptNo: row.orNumber || "",
      paidBy: row.paidBy || "",
      remarks: row.remarks || "",
      paymentDate: row.paymentDate || paymentData.paymentDate,
    });
  };

  const handleOwnerModalClose = () => setShowOwnerModal(false);

  const handleSelectOwner = (owner) => {
    setPaymentData((prev) => ({
      ...prev,
      LOCAL_TIN: owner.LOCAL_TIN || "",
      AFTYPE: "AF56",
      taxpayerName: owner.OWNERNAME || "",
       paidBy: owner.OWNERNAME || "",
    }));

    setJournalData((prev) =>
      prev.map((row) =>
        row.id === selectedRowId
          ? {
              ...row,
              paidBy: owner.OWNERNAME,
              taxpayerName: owner.OWNERNAME,
              localTin: owner.LOCAL_TIN,
              afType: "AF56",
            }
          : row
      )
    );

    setShowOwnerModal(false);
  };

  const handleNewClick = () => {
    const newRow = {
      id: journalData.length + 1,
      cnl: false,
      orNumber: "",
      paidBy: "",
      amount: 0.0,
      paymentDate: paymentData.paymentDate,
      timePaid: "",
      valueDate: "",
      remarks: "",
      taxpayerName: "",
      localTin: "",
      afType: "",
    };

    // const handleOpenGetTaxDue = () => {setShowGetTaxDue(true); };
    // const handleCloseGetTaxDue = () => {setShowGetTaxDue(false); };

    setJournalData((prev) => [...prev, newRow]);
    setSelectedRowId(newRow.id);

    // Enable buttons after new
    setIsNewEnabled(false);
    setIsFindTaxpayerEnabled(true);
    setIsVoidPaymentEnabled(true);
    setIsDeletePaymentEnabled(true);
    setIsGetTaxDueEnabled(true);
    setIsReceiptEnabled(true);

    // Mark form as dirty (unsaved changes)
    setFormDirty(true);

    // Open modal immediately
    setShowOwnerModal(true);
  };



  const handleFindTaxpayerClick = () => setShowOwnerModal(true);

  // Track input changes to mark form dirty
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormDirty(true);
    setPaymentData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // Load payment journal data
  const loadPaymentJournal = async () => {
    try {
      const response = await paymentAPI.getJournal();
      const payments = response.data.map(payment => ({
        id: payment.PAYMENT_ID,
        cnl: false,
        orNumber: payment.RECEIPTNO,
        paidBy: payment.PAIDBY || '',
        amount: parseFloat(payment.AMOUNT) || 0.0,
        paymentDate: payment.PAYMENTDATE,
        timePaid: new Date(payment.PAYMENTDATE).toLocaleTimeString(),
        valueDate: payment.PAYMENTDATE,
        remarks: payment.REMARK || '',
        taxpayerName: payment.taxpayer?.NAME || '',
        localTin: payment.LOCAL_TIN,
        afType: payment.AFTYPE || 'AF56',
      }));
      setJournalData(payments);
    } catch (error) {
      console.error('Error loading payment journal:', error);
    }
  };

  // Save payment to backend
  const savePayment = async () => {
    if (!paymentData.LOCAL_TIN || !paymentData.receiptNo) {
      alert('Please fill in required fields (Local TIN and Receipt No.)');
      return;
    }

    try {
      const paymentPayload = {
        LOCAL_TIN: paymentData.LOCAL_TIN,
        PAYMENTDATE: paymentData.paymentDate,
        AMOUNT: detailsData.reduce((sum, detail) => sum + parseFloat(detail.total || 0), 0),
        RECEIPTNO: paymentData.receiptNo,
        PAYMODE_CT: 'CASH', // You can make this dynamic
        PAIDBY: paymentData.paidBy,
        REMARK: paymentData.remarks,
        AFTYPE: paymentData.AFTYPE,
        details: detailsData.map(detail => ({
          TDNO: detail.taxDecNumber,
          TAX_YEAR: detail.taxYear,
          DESCRIPTION: detail.type,
          QTY: 1,
          UNITPRICE: parseFloat(detail.taxDue || 0),
          AMOUNT: parseFloat(detail.total || 0)
        }))
      };

      const response = await paymentAPI.create(paymentPayload);
      alert('Payment saved successfully! Receipt No: ' + response.data.receipt_no);
      
      // Reset form
      const resetData = {
        LOCAL_TIN: "",
        AFTYPE: "",
        taxpayerName: "",
        receiptNo: "",
        paidBy: "",
        remarks: "",
        paymentDate: new Date().toISOString().split("T")[0],
      };
      
      setPaymentData(resetData);
      setDetailsData([]);
      setFormDirty(false);
      setIsNewEnabled(true);
      setIsFindTaxpayerEnabled(false);
      setIsVoidPaymentEnabled(false);
      setIsDeletePaymentEnabled(false);
      setIsGetTaxDueEnabled(false);
      setIsReceiptEnabled(false);
      
      // Clear localStorage after successful save
      localStorage.removeItem('paymentPostingData');
      localStorage.removeItem('paymentJournalData');
      localStorage.removeItem('paymentDetailsData');
      localStorage.removeItem('paymentFormDirty');
      localStorage.removeItem('paymentButtonStates');
      
      // Reload journal
      loadPaymentJournal();
      
    } catch (error) {
      console.error('Error saving payment:', error);
      alert('Error saving payment: ' + (error.response?.data?.error || error.message));
    }
  };

  // Load persisted state on component mount
  useEffect(() => {
    const savedPaymentData = localStorage.getItem('paymentPostingData');
    const savedJournalData = localStorage.getItem('paymentJournalData');
    const savedDetailsData = localStorage.getItem('paymentDetailsData');
    const savedFormDirty = localStorage.getItem('paymentFormDirty');
    const savedButtonStates = localStorage.getItem('paymentButtonStates');

    if (savedPaymentData) {
      const parsedData = JSON.parse(savedPaymentData);
      // Always use current date for payment date
      const today = new Date().toISOString().split("T")[0];
      setPaymentData({ ...parsedData, paymentDate: today });
    } else {
      const today = new Date().toISOString().split("T")[0];
      setPaymentData((prev) => ({ ...prev, paymentDate: today }));
    }

    if (savedJournalData) {
      setJournalData(JSON.parse(savedJournalData));
    }

    if (savedDetailsData) {
      setDetailsData(JSON.parse(savedDetailsData));
    }

    if (savedFormDirty) {
      setFormDirty(JSON.parse(savedFormDirty));
    }

    if (savedButtonStates) {
      const states = JSON.parse(savedButtonStates);
      setIsNewEnabled(states.isNewEnabled);
      setIsFindTaxpayerEnabled(states.isFindTaxpayerEnabled);
      setIsVoidPaymentEnabled(states.isVoidPaymentEnabled);
      setIsDeletePaymentEnabled(states.isDeletePaymentEnabled);
      setIsGetTaxDueEnabled(states.isGetTaxDueEnabled);
      setIsReceiptEnabled(states.isReceiptEnabled);
    }

    // loadPaymentJournal(); // Disabled until data migration is complete
  }, []);

  // Save payment data to localStorage whenever it changes
  useEffect(() => {
    localStorage.setItem('paymentPostingData', JSON.stringify(paymentData));
  }, [paymentData]);

  // Save journal data to localStorage whenever it changes
  useEffect(() => {
    localStorage.setItem('paymentJournalData', JSON.stringify(journalData));
  }, [journalData]);

  // Save details data to localStorage whenever it changes
  useEffect(() => {
    localStorage.setItem('paymentDetailsData', JSON.stringify(detailsData));
  }, [detailsData]);

  // Save form dirty state to localStorage whenever it changes
  useEffect(() => {
    localStorage.setItem('paymentFormDirty', JSON.stringify(formDirty));
  }, [formDirty]);

  // Save button states to localStorage whenever they change
  useEffect(() => {
    const buttonStates = {
      isNewEnabled,
      isFindTaxpayerEnabled,
      isVoidPaymentEnabled,
      isDeletePaymentEnabled,
      isGetTaxDueEnabled,
      isReceiptEnabled
    };
    localStorage.setItem('paymentButtonStates', JSON.stringify(buttonStates));
  }, [isNewEnabled, isFindTaxpayerEnabled, isVoidPaymentEnabled, isDeletePaymentEnabled, isGetTaxDueEnabled, isReceiptEnabled]);

  //  Refresh Warning
  useEffect(() => {
    const handleBeforeUnload = (event) => {
      if (formDirty && !isNewEnabled) {
        event.preventDefault();
        event.returnValue =
          "Refreshing will clear your entries. Are you sure?";
        return event.returnValue;
      }
    };

    window.addEventListener("beforeunload", handleBeforeUnload);
    return () => {
      window.removeEventListener("beforeunload", handleBeforeUnload);
    };
  }, [formDirty, isNewEnabled]);


  return (
    <div className="payment-posting-container">
      {/* = JOURNAL SECTION = */}
      <div className="journal-section">
        <h4>Journal</h4>
        <div className="table-wrapper fixed-header">
          <table>
            <thead>
              <tr>
                <th className="arrow-col"></th>
                <th>CNL</th>
                <th>O.R. Number</th>
                <th>Paid By</th>
                <th>Amount</th>
                <th>Payment Date</th>
                <th>Time Paid</th>
                <th>Value Date</th>
                <th>Remarks</th>
                <th>Taxpayer's Name</th>
              </tr>
            </thead>
            <tbody>
              {journalData.length > 0 ? (
                journalData.map((row) => (
                  <tr
                    key={row.id}
                    onClick={() => handleJournalClick(row)}
                    className={selectedRowId === row.id ? "selected-row" : ""}
                  >
                    <td className="arrow-col">
                      {selectedRowId === row.id ? "▶" : ""}
                    </td>
                    <td>
                      <input
                        type="checkbox"
                        checked={row.cnl}
                        onChange={(e) =>
                          setJournalData((prev) =>
                            prev.map((r) =>
                              r.id === row.id
                                ? { ...r, cnl: e.target.checked }
                                : r
                            )
                          )
                        }
                      />
                    </td>
                    <td>{row.orNumber}</td>
                    <td>{row.paidBy}</td>
                    <td>{row.amount.toFixed(2)}</td>
                    <td>{row.paymentDate}</td>
                    <td>{row.timePaid}</td>
                    <td>{row.valueDate}</td>
                    <td>{row.remarks}</td>
                    <td>{row.taxpayerName}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="10" style={{ textAlign: "center" }}>
                    No records found
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* = PAYMENT INFO = */}
      <div className="payment-info-section">
        <h4>Payment Information</h4>
        <div className="form-grid">
          <label>Local TIN:</label>
          <input type="text" value={paymentData.LOCAL_TIN} readOnly />

          <label>AF Type:</label>
          <input type="text" value={paymentData.AFTYPE} readOnly />

          <label>Taxpayer:</label>
          <input
            type="text"
            value={paymentData.taxpayerName}
            readOnly
            className="full-width"
          />

          <label>Receipt No.:</label>
          <input
            name="receiptNo"
            value={paymentData.receiptNo}
            onChange={handleInputChange}
          />


          <label>Format:</label>
          <div className="format-group">
            <select className="format-select">
              <option>Format A</option>
              <option>Format A (Detailed)</option>
              <option>Format B</option>
              <option>Format B (Detailed)</option>
            </select>
            <label>
              <input type="checkbox" /> Yearly
            </label>
            <label>
              <input type="checkbox" /> Qtr
            </label>
          </div>

          <label>Payment Mode:</label>
          <select>
            <option>CASH</option>
            <option>CHECK</option>
            <option>CASH and CHECK</option>
          </select>

          <label>Pay Type:</label>
          <select>
            <option>Real Property Tax</option>
            <option>Other Payments</option>
          </select>

          <label>Paid By:</label>
          <input
            name="paidBy"
            className="full-width"
            value={paymentData.paidBy}
            onChange={handleInputChange}
          />

          <label>Remarks:</label>
          <input
            name="remarks"
            className="full-width"
            value={paymentData.remarks}
            onChange={handleInputChange}
          />
        </div>
      </div>

      {/* = DETAILS SECTION = */}
      <div className="details-section">
        <h4>Details</h4>
        <div className="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Tax Year</th>
                <th>Tax Dec. Number</th>
                <th>Type</th>
                <th>Tax Due</th>
                <th>Pen/Disc</th>
                <th>Total</th>
                <th>Term</th>
              </tr>
            </thead>
            <tbody>
              {detailsData.length > 0 ? (
                detailsData.map((row, index) => (
                  <tr key={index}>
                    <td>{row.taxYear}</td>
                    <td>{row.taxDecNumber}</td>
                    <td>{row.type}</td>
                    <td>{row.taxDue}</td>
                    <td>{row.penDisc}</td>
                    <td>{row.total}</td>
                    <td>{row.term}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="7" style={{ textAlign: "center" }}>
                    No details found
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="total-amount">
          <strong>Total Amount Due:</strong> {detailsData.reduce((sum, detail) => sum + parseFloat(detail.total || 0), 0).toFixed(2)}
        </div>
      </div>

      {/* = BOTTOM CONTROLS = */}
      <div className="bottom-controls">
        <div className="bottom-left">
          <div className="top-row">
            <label>Payment Date:</label>
            <input
              type="date"
              value={paymentData.paymentDate}
              onChange={(e) =>
                setPaymentData((prev) => ({ ...prev, paymentDate: e.target.value }))
              }
              disabled={!isNewEnabled}
            />
            <label>
              <input type="checkbox" /> All Users
            </label>
          </div>

          <div className="button-row">
            <button
              onClick={handleNewClick}
              disabled={!isNewEnabled}
              className={!isNewEnabled ? "disabled-btn" : ""}
            >
              New
            </button>
            <button
              onClick={handleFindTaxpayerClick}
              disabled={!isFindTaxpayerEnabled}
              className={!isFindTaxpayerEnabled ? "disabled-btn" : ""}
            >
              Find Taxpayer
            </button>
            <button 
              className="btn btn-primary"
              disabled={!isVoidPaymentEnabled}
            >
              Void Payment
            </button>
            <button 
              className="btn btn-danger"
              disabled={!isDeletePaymentEnabled}
            >
              Delete Payment
            </button>
          </div>
        </div>

        <div className="bottom-right">
          <button 
            className="btn btn-primary"
            onClick={handleOpenGetTaxDue}
            disabled={!isGetTaxDueEnabled}
          >
            Get Tax Due
          </button>
          <button 
            className="btn btn-success"
            disabled={!isReceiptEnabled}
          >
            Receipt
          </button>
          <button 
            className="btn btn-danger"
            onClick={() => {
              const resetData = {
                LOCAL_TIN: "",
                AFTYPE: "",
                taxpayerName: "",
                receiptNo: "",
                paidBy: "",
                remarks: "",
                paymentDate: new Date().toISOString().split("T")[0],
              };
              
              setPaymentData(resetData);
              setDetailsData([]);
              setFormDirty(false);
              setIsNewEnabled(true);
              setIsFindTaxpayerEnabled(false);
              setIsVoidPaymentEnabled(false);
              setIsDeletePaymentEnabled(false);
              setIsGetTaxDueEnabled(false);
              setIsReceiptEnabled(false);
              
              // Clear localStorage
              localStorage.removeItem('paymentPostingData');
              localStorage.removeItem('paymentJournalData');
              localStorage.removeItem('paymentDetailsData');
              localStorage.removeItem('paymentFormDirty');
              localStorage.removeItem('paymentButtonStates');
            }}
          >
            Cancel
          </button>
        </div>
      </div>

      {/* = OWNER SEARCH MODAL = */}
      {showOwnerModal && (
        <OwnerSearchModal
          onClose={handleOwnerModalClose}
          onSelectOwner={handleSelectOwner}
        />
      )}

      {showGetTaxDue && (
        <GetTaxDueModal 
        onClose={handleCloseGetTaxDue} 
        localTin={paymentData.LOCAL_TIN}
        ownerName={paymentData.taxpayerName}
        
        />
      )}
    </div>
  );
};

export default PaymentPostingPage;
