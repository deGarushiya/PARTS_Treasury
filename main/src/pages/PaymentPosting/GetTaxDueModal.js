import React, { useEffect, useState } from "react";
import "./GetTaxDueModal.css";
import { taxDueAPI } from "../../services/api";
import RecalculatePenaltyModal from "./RecalculatePenaltyModal";

const GetTaxDueModal = ({ onClose, localTin, ownerName }) => {
  const [properties, setProperties] = useState([]);
  const [dues, setDues] = useState([]);
  const [selectedTdno, setSelectedTdno] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [loadingProps, setLoadingProps] = useState(false);
  const [loadingDues, setLoadingDues] = useState(false);
  const [errorProps, setErrorProps] = useState(null);
  const [errorDues, setErrorDues] = useState(null);
  
  // State for checkboxes
  const [selectedProperties, setSelectedProperties] = useState([]);
  const [selectedDues, setSelectedDues] = useState([]);
  const [selectAllProperties, setSelectAllProperties] = useState(false);
  const [selectAllDues, setSelectAllDues] = useState(false);
  const [unselectAllBtnText, setUnselectAllBtnText] = useState("Unselect All");
  
  // State for multi-selected rows (highlighting - like Excel)
  const [highlightedPropertyIndices, setHighlightedPropertyIndices] = useState([]);
  const [highlightedDueIndices, setHighlightedDueIndices] = useState([]);
  
  // State for Recalculate Penalty modal
  const [showRecalcModal, setShowRecalcModal] = useState(false);

   useEffect(() => {
    if (!localTin) return;

    const fetchProperties = async () => {
      setLoadingProps(true);
      try {
        const response = await taxDueAPI.getProperties(localTin);
        const propsData = Array.isArray(response.data) ? response.data : [];
        setProperties(propsData);
        
        // Auto-check all properties and highlight first one
        if (propsData.length > 0) {
          setSelectedProperties(propsData.map((_, index) => index));
          setSelectAllProperties(true);
          setSelectedTdno(propsData[0].TDNO);
          setHighlightedPropertyIndices([0]); // Highlight first row
        }
      } catch (err) {
        console.error("Error fetching properties:", err);
        setErrorProps("Failed to fetch properties.");
      } finally {
        setLoadingProps(false);
      }
    };

    fetchProperties();
  }, [localTin]);


  // Fetch combined dues from ALL checked properties
  const fetchCombinedDues = async () => {
    if (!localTin || selectedProperties.length === 0) {
      setDues([]);
      setSelectedDues([]);
      setSelectAllDues(false);
      return;
    }

    console.log(`🔍 Fetching combined tax dues for ${selectedProperties.length} checked properties`);
    setLoadingDues(true);
    setErrorDues(null);
    
    try {
      // Get TDNOs of all checked properties
      const checkedTdnos = selectedProperties.map(index => properties[index].TDNO);
      console.log('📋 Checked TDNOs:', checkedTdnos);
      
      // Fetch dues for each TDNO
      const allDuesPromises = checkedTdnos.map(tdno => 
        taxDueAPI.getTaxDueByTdno(localTin, tdno)
      );
      
      const allDuesResponses = await Promise.all(allDuesPromises);
      
      // Combine all dues into one array
      const combinedDues = [];
      allDuesResponses.forEach(response => {
        const duesData = Array.isArray(response.data) ? response.data : [];
        combinedDues.push(...duesData);
      });
      
      console.log(`📊 Combined ${combinedDues.length} tax dues from all checked properties`);
      setDues(combinedDues);
      
      // Auto-select all dues when loaded
      if (combinedDues.length > 0) {
        setSelectedDues(combinedDues.map((_, index) => index));
        setSelectAllDues(true);
      } else {
        setSelectedDues([]);
        setSelectAllDues(false);
      }
      
      // Clear lower table highlight
      setHighlightedDueIndices([]);
    } catch (err) {
      console.error("❌ Error fetching combined tax dues:", err);
      setErrorDues("Failed to fetch tax due data.");
    } finally {
      setLoadingDues(false);
    }
  };

  // Auto-fetch combined dues whenever selectedProperties changes
  useEffect(() => {
    if (properties.length > 0) {
      fetchCombinedDues();
    }
  }, [selectedProperties, properties]);

  // Handle select all properties
  const handleSelectAllProperties = (e) => {
    const checked = e.target.checked;
    setSelectAllProperties(checked);
    if (checked) {
      setSelectedProperties(properties.map((_, index) => index));
    } else {
      setSelectedProperties([]);
    }
  };

  // Handle individual property selection
  const handlePropertyCheckbox = (index, e) => {
    e.stopPropagation(); // Prevent row click
    
    // Highlight the row that was checked/unchecked
    setSelectedTdno(properties[index].TDNO);
    setHighlightedPropertyIndices([index]);
    
    if (selectedProperties.includes(index)) {
      setSelectedProperties(selectedProperties.filter(i => i !== index));
      setSelectAllProperties(false);
    } else {
      const newSelected = [...selectedProperties, index];
      setSelectedProperties(newSelected);
      setSelectAllProperties(newSelected.length === properties.length);
    }
  };

  // Handle select all dues
  const handleSelectAllDues = (e) => {
    const checked = e.target.checked;
    setSelectAllDues(checked);
    if (checked) {
      setSelectedDues(dues.map((_, index) => index));
    } else {
      setSelectedDues([]);
    }
  };

  // Handle individual due selection
  const handleDueCheckbox = (index, e) => {
    e.stopPropagation();
    
    // Highlight the row that was checked/unchecked
    setHighlightedDueIndices([index]);
    
    if (selectedDues.includes(index)) {
      setSelectedDues(selectedDues.filter(i => i !== index));
      setSelectAllDues(false);
    } else {
      const newSelected = [...selectedDues, index];
      setSelectedDues(newSelected);
      setSelectAllDues(newSelected.length === dues.length);
    }
  };

  // Update button text based on selection state (only for dues table)
  useEffect(() => {
    const allDuesSelected = dues.length > 0 && selectedDues.length === dues.length;

    if (allDuesSelected) {
      setUnselectAllBtnText("Unselect All");
    } else {
      setUnselectAllBtnText("Select All");
    }
  }, [selectedDues, dues]);

  // Handle "Unselect All" button (only affects Tax Dues table - lower table)
  const handleUnselectAll = () => {
    // Check if all dues are currently selected
    const allDuesSelected = selectedDues.length === dues.length;

    if (allDuesSelected) {
      // Unselect all dues
      setSelectedDues([]);
      setSelectAllDues(false);
    } else {
      // Select all dues
      setSelectedDues(dues.map((_, index) => index));
      setSelectAllDues(true);
    }
  };

  // Handle "Compute PEN" button
  const handleComputePEN = () => {
    if (!localTin) return;
    setShowRecalcModal(true); // Show the date picker modal
  };

  // Handle confirmation from Recalculate Penalty modal
  const handleRecalcConfirm = async (postingDate, month, year) => {
    setLoadingDues(true);
    try {
      const response = await taxDueAPI.computePenaltyDiscount(localTin, postingDate);
      console.log('✅ Compute PEN response:', response.data);
      alert(`Success!\nComputed for: ${["January","February","March","April","May","June","July","August","September","October","November","December"][month-1]} ${year}\nDeleted: ${response.data.deleted} old PEN/DED records\nInserted: ${response.data.inserted} new PEN/DED records`);
      
      // Refresh the dues table
      await fetchCombinedDues();
    } catch (err) {
      console.error('❌ Error computing PEN:', err);
      alert('Failed to compute penalties/discounts: ' + (err.response?.data?.error || err.message));
    } finally {
      setLoadingDues(false);
    }
  };

  // Handle "Remove PEN" button
  const handleRemovePEN = async () => {
    if (!localTin) return;
    
    const confirmed = window.confirm('This will remove all penalty/discount records. Continue?');
    if (!confirmed) return;

    setLoadingDues(true);
    try {
      const response = await taxDueAPI.removePenaltyDiscount(localTin);
      console.log('✅ Remove PEN response:', response.data);
      alert(`Success!\nRemoved ${response.data.deleted} PEN/DED records`);
      
      // Refresh the dues table
      await fetchCombinedDues();
    } catch (err) {
      console.error('❌ Error removing PEN:', err);
      alert('Failed to remove penalties/discounts: ' + (err.response?.data?.error || err.message));
    } finally {
      setLoadingDues(false);
    }
  };

  // Handle "Tax Credit" button
  const handleTaxCredit = async () => {
    if (!localTin || dues.length === 0) {
      alert('No tax dues available to apply credit to.');
      return;
    }

    // Get highlighted due (if any)
    const highlightedDue = highlightedDueIndices.length > 0 ? dues[highlightedDueIndices[0]] : null;
    
    if (!highlightedDue) {
      alert('Please select a tax due row first by clicking on it.');
      return;
    }

    const creditAmount = prompt(`Enter tax credit amount for:\nYear: ${highlightedDue.TAX_YEAR}\nTD: ${highlightedDue.TDNO}\nCurrent Due: ${parseFloat(highlightedDue.total_tax_due || 0).toFixed(2)}`);
    
    if (!creditAmount || isNaN(creditAmount) || parseFloat(creditAmount) <= 0) {
      return; // User cancelled or invalid input
    }

    setLoadingDues(true);
    try {
      const response = await taxDueAPI.addTaxCredit({
        local_tin: localTin,
        prop_id: highlightedDue.PROP_ID,
        tax_year: highlightedDue.TAX_YEAR,
        tax_period: highlightedDue.TAXPERIOD_CT || 99,
        journal_id: highlightedDue.POSTINGJOURNAL_ID,
        credit_amount: parseFloat(creditAmount)
      });
      
      console.log('✅ Tax Credit response:', response.data);
      alert(`Success!\nAdded tax credit of ${parseFloat(creditAmount).toFixed(2)}`);
      
      // Refresh the dues table
      await fetchCombinedDues();
    } catch (err) {
      console.error('❌ Error adding tax credit:', err);
      alert('Failed to add tax credit: ' + (err.response?.data?.error || err.message));
    } finally {
      setLoadingDues(false);
    }
  };

  // Handle "Remove Credits" button
  const handleRemoveCredits = async () => {
    if (!localTin) return;
    
    const confirmed = window.confirm('This will remove all tax credit records. Continue?');
    if (!confirmed) return;

    setLoadingDues(true);
    try {
      const response = await taxDueAPI.removeCredits(localTin);
      console.log('✅ Remove Credits response:', response.data);
      alert(`Success!\nRemoved ${response.data.deleted} tax credit records`);
      
      // Refresh the dues table
      await fetchCombinedDues();
    } catch (err) {
      console.error('❌ Error removing credits:', err);
      alert('Failed to remove credits: ' + (err.response?.data?.error || err.message));
    } finally {
      setLoadingDues(false);
    }
  };

  return (
    <div className="gettaxdue-modal-backdrop">
      <div className="gettaxdue-modal">
        {/* Header */}
        <div className="modal-header">
          <h3>Taxpayer Debit</h3>
          <button className="close-btn" onClick={onClose}>×</button>
        </div>

        {/* Main Content */}
        <div className="modal-body">
          {/* LEFT COLUMN */}
          <div className="left-column">
            {/* Taxpayer Info */}
            <div className="taxpayer-info">
              <div><strong>Local TIN:</strong> {localTin}</div>
              <div><strong>Owner Name:</strong> {ownerName}</div>
            </div>

            {/* List of Properties */}
            <div className="section">
              <h5>List of Properties:</h5>
              <div className="table-container properties-table-container">
                <table className="modern-table">
                  <thead>
                    <tr>
                      <th className="checkbox-column">
                        <input 
                          type="checkbox" 
                          checked={selectAllProperties}
                          onChange={handleSelectAllProperties}
                        />
                      </th>
                      <th>Tax Declaration No.</th>
                      <th>PIN</th>
                      <th>Barangay</th>
                      <th>Property Kind</th>
                      <th>Expired Property</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loadingProps ? (
                      <tr><td colSpan="6" className="center-text">Loading...</td></tr>
                    ) : errorProps ? (
                      <tr><td colSpan="6" className="center-text" style={{ color: "red" }}>{errorProps}</td></tr>
                    ) : properties.length === 0 ? (
                      <tr><td colSpan="6" className="center-text">No records found</td></tr>
                    ) : (
                      properties.map((item, index) => (
                        <tr
                          key={index}
                          className={`clickable-row ${highlightedPropertyIndices.includes(index) ? 'selected-row' : ''}`}
                          onClick={(e) => {
                            setSelectedTdno(item.TDNO);
                            
                            // Ctrl+Click = Multi-select (like Excel - doesn't affect checkboxes)
                            if (e.ctrlKey) {
                              if (highlightedPropertyIndices.includes(index)) {
                                // Remove from highlight
                                setHighlightedPropertyIndices(highlightedPropertyIndices.filter(i => i !== index));
                              } else {
                                // Add to highlight
                                setHighlightedPropertyIndices([...highlightedPropertyIndices, index]);
                              }
                            } else {
                              // Normal click = Single select
                              setHighlightedPropertyIndices([index]);
                            }
                          }}
                        >
                          <td className="checkbox-column">
                            <input 
                              type="checkbox" 
                              checked={selectedProperties.includes(index)}
                              onChange={(e) => handlePropertyCheckbox(index, e)}
                              onClick={(e) => e.stopPropagation()}
                            />
                          </td>
                          <td>{item.TDNO}</td>
                          <td>{item.PIN}</td>
                          <td>{item.BarangayName}</td>
                          <td>{item.PropertyKind}</td>
                          <td><input type="checkbox" checked={!!item.expired} readOnly /></td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Tax Dues Table */}
            <div className="section">
              <div className="table-container dues-table-container">
                <table className="modern-table">
                  <thead>
                    <tr>
                      <th className="checkbox-column">
                        <input 
                          type="checkbox" 
                          checked={selectAllDues}
                          onChange={handleSelectAllDues}
                        />
                      </th>
                      <th>Tax Year</th>
                      <th>TD Number</th>
                      <th>Amount Due</th>
                      <th>Penalty/Discount</th>
                      <th>Credits</th>
                      <th>Total Tax Due</th>
                      <th>Period</th>
                      <th></th>
                      <th>Amount</th>
                      <th>Booking Reference</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loadingDues ? (
                      <tr><td colSpan="11" className="center-text">Loading...</td></tr>
                    ) : errorDues ? (
                      <tr><td colSpan="11" className="center-text" style={{ color: "red" }}>{errorDues}</td></tr>
                    ) : dues.length === 0 ? (
                      <tr>
                        <td colSpan="11" className="center-text" style={{ padding: "20px" }}>
                          <div style={{ color: "#666", fontSize: "14px" }}>
                            <strong>No Tax Dues Found</strong>
                            <br />
                            <span style={{ fontSize: "13px" }}>
                              This property has no posted tax bills yet. Possible reasons:
                              <br />
                              • Tax bill has not been officially posted
                              <br />
                              • All dues have been fully paid
                              <br />
                              • Tax bill was cancelled or exempted
                            </span>
                          </div>
                        </td>
                      </tr>
                    ) : (
                      dues.map((due, index) => (
                        <tr 
                          key={index} 
                          className={`clickable-row ${highlightedDueIndices.includes(index) ? 'selected-row' : ''}`}
                          onClick={(e) => {
                            // Ctrl+Click = Multi-select (like Excel - doesn't affect checkboxes)
                            if (e.ctrlKey) {
                              if (highlightedDueIndices.includes(index)) {
                                // Remove from highlight
                                setHighlightedDueIndices(highlightedDueIndices.filter(i => i !== index));
                              } else {
                                // Add to highlight
                                setHighlightedDueIndices([...highlightedDueIndices, index]);
                              }
                            } else {
                              // Normal click = Single select
                              setHighlightedDueIndices([index]);
                            }
                          }}
                        >
                          <td className="checkbox-column">
                            <input 
                              type="checkbox" 
                              checked={selectedDues.includes(index)}
                              onChange={(e) => handleDueCheckbox(index, e)}
                              onClick={(e) => e.stopPropagation()}
                            />
                          </td>
                          <td>{due.TAX_YEAR}</td>
                          <td>{due.TDNO || 'N/A'}</td>
                          <td className="text-right">{parseFloat(due.amount_due || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                          <td className="text-right">{parseFloat(due.penalty_discount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                          <td className="text-right">{parseFloat(due.credits || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                          <td className="text-right"><strong>{parseFloat(due.total_tax_due || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></td>
                          <td>{(due.period || 'YEARLY ASS').replace(' ASS','')}</td>
                          <td>ASS</td>
                          <td className="text-right">
                            {selectedDues.includes(index) 
                              ? parseFloat(due.total_tax_due || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                              : '0.00'
                            }
                          </td>
                          <td>{due.booking_reference || ''}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Total Amount Due */}
            <div className="totals-section">
              <div className="total-row">
                <span className="total-label">Total Amount Due:</span>
                <div className="total-values">
                  <span className="total-value">
                    {dues.reduce((sum, due) => sum + parseFloat(due.total_tax_due || 0), 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </span>
                  <span className="total-value selected-total">
                    {dues.reduce((sum, due, index) => {
                      return sum + (selectedDues.includes(index) ? parseFloat(due.total_tax_due || 0) : 0);
                    }, 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* RIGHT COLUMN */}
          <div className="right-column">
            <div className="top-buttons">
              <button className="blue-btn">Return</button>
              <button className="blue-btn">Print Tax Bill</button>
            </div>

            <div className="bottom-buttons">
              <button className="blue-btn" onClick={handleUnselectAll}>
                {unselectAllBtnText}
              </button>
              <button className="blue-btn" onClick={handleComputePEN}>Compute PEN</button>
              <button className="blue-btn" onClick={handleRemovePEN}>Remove PEN</button>
              <button className="blue-btn" onClick={handleTaxCredit}>Tax Credit</button>
              <button className="blue-btn" onClick={handleRemoveCredits}>Remove Credits</button>
              <button className="blue-btn">Bi-Annual</button>
              <button className="blue-btn">Quarterly</button>
              <button className="blue-btn">Undo Division</button>
            </div>
          </div>
        </div>
      </div>

      {/* Recalculate Penalty Modal */}
      {showRecalcModal && (
        <RecalculatePenaltyModal
          onClose={() => setShowRecalcModal(false)}
          onConfirm={handleRecalcConfirm}
        />
      )}
    </div>
  );
};

export default GetTaxDueModal;
