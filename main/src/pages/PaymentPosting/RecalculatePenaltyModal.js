import React, { useState } from "react";
import "./RecalculatePenaltyModal.css";

const RecalculatePenaltyModal = ({ onClose, onConfirm }) => {
  const currentDate = new Date();
  const [selectedMonth, setSelectedMonth] = useState(currentDate.getMonth() + 1); // 1-12
  const [selectedYear, setSelectedYear] = useState(currentDate.getFullYear());

  const months = [
    { value: 1, label: "January" },
    { value: 2, label: "February" },
    { value: 3, label: "March" },
    { value: 4, label: "April" },
    { value: 5, label: "May" },
    { value: 6, label: "June" },
    { value: 7, label: "July" },
    { value: 8, label: "August" },
    { value: 9, label: "September" },
    { value: 10, label: "October" },
    { value: 11, label: "November" },
    { value: 12, label: "December" }
  ];

  const handleOK = () => {
    // Create a date string for the selected month/year
    const postingDate = `${selectedYear}-${String(selectedMonth).padStart(2, '0')}-01`;
    onConfirm(postingDate, selectedMonth, selectedYear);
    onClose();
  };

  return (
    <div className="recalc-modal-backdrop" onClick={onClose}>
      <div className="recalc-modal" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="recalc-header">
          <h3>Recalculate Penalty / Discount</h3>
          <button className="recalc-close-btn" onClick={onClose}>×</button>
        </div>

        {/* Body */}
        <div className="recalc-body">
          <div className="recalc-section">
            <h4>Period of Computation</h4>
            
            <div className="recalc-inputs">
              <div className="recalc-field">
                <label>Month:</label>
                <select 
                  value={selectedMonth} 
                  onChange={(e) => setSelectedMonth(Number(e.target.value))}
                >
                  {months.map(month => (
                    <option key={month.value} value={month.value}>
                      {month.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="recalc-field">
                <label>Year:</label>
                <input 
                  type="number" 
                  value={selectedYear}
                  onChange={(e) => setSelectedYear(Number(e.target.value))}
                  min="2000"
                  max="2099"
                />
              </div>
            </div>
          </div>

          <div className="recalc-actions">
            <button className="recalc-ok-btn" onClick={handleOK}>OK</button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RecalculatePenaltyModal;


