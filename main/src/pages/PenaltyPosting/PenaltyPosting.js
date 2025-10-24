import React, { useState, useEffect } from "react";
import "./PenaltyPosting.css";
import PropertySearchModal from "../../components/PropertySearchModal/PropertySearchModal";

const PenaltyPosting = () => {
  const [barangays, setBarangays] = useState([]);
  const [searchBarangay, setSearchBarangay] = useState("");
  const [penalty, setPenalty] = useState([]);
  const [selectedOption, setSelectedOption] = useState("property"); // Default: Per Property selected
  const [selectedDate, setSelectedDate] = useState(() => {
    // Set to current month (YYYY-MM format)
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
  });
  const [loading, setLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [showPropertyModal, setShowPropertyModal] = useState(false);
  const [isPosting, setIsPosting] = useState(false);
  const [postingProgress, setPostingProgress] = useState({ current: 0, total: 0, taxtransId: '' });
  const [showCompletionModal, setShowCompletionModal] = useState(false);

  useEffect(() => {
    fetch("http://127.0.0.1:8000/api/barangays")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch barangays");
        return res.json();
      })
      .then((data) => setBarangays(data))
      .catch((err) => console.error("Barangay fetch error:", err));
  }, []);

  // Auto-fetch data when barangay is selected (Per Barangay mode)
  useEffect(() => {
    if (selectedOption === "barangay" && searchBarangay) {
      // Fetch penalty records for selected barangay
      setLoading(true);
      setStatusMessage("Loading penalty records...");

      fetch(`http://127.0.0.1:8000/api/penalty?barangay=${searchBarangay}`)
        .then((res) => {
          if (!res.ok) throw new Error("Failed to fetch penalty data");
          return res.json();
        })
        .then((data) => {
          // Sort by TDNO automatically for barangay results
          const sortedData = data.sort((a, b) => {
            if (a.TDNO < b.TDNO) return -1;
            if (a.TDNO > b.TDNO) return 1;
            return 0;
          });
          setPenalty(sortedData);
          setStatusMessage(`Found ${sortedData.length} record(s) for ${searchBarangay}`);
          setTimeout(() => setStatusMessage(""), 3000);
        })
        .catch((err) => {
          console.error("Penalty fetch error:", err);
          setStatusMessage("Error loading penalty data");
          setTimeout(() => setStatusMessage(""), 3000);
        })
        .finally(() => {
          setLoading(false);
        });
    } else if (selectedOption === "barangay" && !searchBarangay) {
      setPenalty([]); // Clear data if barangay is deselected
    }
  }, [selectedOption, searchBarangay]);

  // Function to open Property Search Modal when Search button is clicked
  const handleSearch = () => {
    setShowPropertyModal(true);
  };

  // Handle property selection from Property Search Modal
  const handlePropertySelect = (property) => {
    console.log("Selected property:", property);
    setShowPropertyModal(false);
    
    // Fetch penalty records for the selected property
    setLoading(true);
    setStatusMessage("Loading penalty records...");

    // Build query params - filter by TDNO
    let queryParams = `tdno=${property.TDNO}`;

    fetch(`http://127.0.0.1:8000/api/penalty?${queryParams}`)
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch penalty data");
        return res.json();
      })
      .then((data) => {
        // Add new results to existing data (accumulate) instead of replacing
        setPenalty(prevPenalty => {
          const newTotal = prevPenalty.length + data.length;
          setStatusMessage(`Added ${data.length} record(s) for ${property.TDNO}. Total: ${newTotal} record(s)`);
          setTimeout(() => setStatusMessage(""), 3000);
          return [...prevPenalty, ...data];
        });
      })
      .catch((err) => {
        console.error("Penalty fetch error:", err);
        setStatusMessage("Error loading penalty data");
        setTimeout(() => setStatusMessage(""), 3000);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  // Handle Post button click
  const handlePost = async () => {
    if (penalty.length === 0) {
      alert("No records found.");
      return;
    }

    if (!window.confirm(`Post penalties for ${penalty.length} record(s)?`)) {
      return;
    }

    setIsPosting(true);
    setPostingProgress({ current: 0, total: penalty.length, taxtransId: '' });

    try {
      const BATCH_SIZE = 50; // Process 50 records at a time
      const totalRecords = penalty.length;
      let processedCount = 0;

      // Process in batches
      for (let i = 0; i < totalRecords; i += BATCH_SIZE) {
        const batch = penalty.slice(i, Math.min(i + BATCH_SIZE, totalRecords));
        const batchEnd = Math.min(i + BATCH_SIZE, totalRecords);
        
        // Show smooth progress within the batch
        for (let j = 0; j < batch.length; j++) {
          const currentIndex = i + j;
          setPostingProgress({ 
            current: currentIndex + 1, 
            total: totalRecords, 
            taxtransId: batch[j].TAXTRANS_ID 
          });
          
          // Delay to show visible progress (100ms per record = ~5 seconds per batch of 50)
          await new Promise(resolve => setTimeout(resolve, 100));
        }

        // Send batch to backend (processes all 50 at once, very fast!)
        const response = await fetch('http://127.0.0.1:8000/api/penalty/post', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            records: batch, // Send batch of records
            asOfDate: selectedDate,
          }),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.error || 'Failed to process batch');
        }

        processedCount = batchEnd;
      }

      // All done!
      setShowCompletionModal(true);
      setStatusMessage(`Penalty posting completed. Processed: ${totalRecords}/${totalRecords}`);
      setTimeout(() => setStatusMessage(""), 3000);
      
    } catch (error) {
      console.error("Post error:", error);
      alert('Error posting penalties: ' + error.message);
      setStatusMessage("Error posting penalties");
    } finally {
      setIsPosting(false);
      setPostingProgress({ current: 0, total: 0, taxtransId: '' }); // Clear progress
    }
  };

  return (
    <div className="all card">
      <div className="top-panel">
        <div className="date">
            <label>As of: </label>
            <input
            type="month"
            className="date-input"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            />
        </div>

        <div className="radio-row">
          <label>
            <input
              type="radio"
              name="searchType"
              value="property"
              checked={selectedOption === "property"}
              onChange={(e) => setSelectedOption(e.target.value)}
            />{" "}
            Per Property
          </label>
          <label>
            <input
              type="radio"
              name="searchType"
              value="barangay"
              checked={selectedOption === "barangay"}
              onChange={(e) => setSelectedOption(e.target.value)}
            />{" "}
            Per Barangay
          </label>
        </div>

        <div>
          <form>
            <fieldset>
              <legend>Barangay:</legend>
              <select
                value={searchBarangay}
                onChange={(e) => setSearchBarangay(e.target.value)}
                disabled={selectedOption === "property"}
              >
                <option value=""></option>
                {barangays.map((barangay) => (
                  <option key={barangay.code} value={barangay.code}>
                    {barangay.description}
                  </option>
                ))}
              </select>
            </fieldset>
          </form>
        </div>
      </div>

      {/* Progress text - shown below the top panel */}
      {isPosting && (
        <div className="progress-info">
          Processing {postingProgress.current} of {postingProgress.total}. Taxtrans ID: {postingProgress.taxtransId}
        </div>
      )}

      <div className="result-table" style={{ position: 'relative' }}>
        {loading && (
          <div className="loading-overlay">
            <div className="loading-spinner"></div>
            <div className="loading-text">Loading penalty records...</div>
          </div>
        )}
        <table>
          <thead>
            <tr>
              <th>TD No</th>
              <th>Tax Year</th>
              <th>Owner Name</th>
              <th>Barangay</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {penalty.length > 0 ? (
                penalty.map((p, index) => (
                <tr key={`${p.TDNO}-${index}`}>
                    <td title={p.TDNO}>{p.TDNO}</td>
                    <td title={p.TAXYEAR}>{p.TAXYEAR}</td>
                    <td title={p.OWNERNAME}>{p.OWNERNAME}</td>
                    <td title={p.barangay}>{p.barangay}</td>
                    <td title="OPEN">OPEN</td>
                </tr>
                ))
            ) : (
                <tr>
                    <td colSpan="5" style={{ textAlign: "center", color: "#999"}}>
                        <b>{loading ? '' : 'NO DATA FOUND'}</b>
                    </td>
                </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="footer-section">
        <div className="footer-layout">
          <button 
            onClick={handleSearch}
            disabled={selectedOption !== "property"}
            className="footer-btn"
          >
            Search
          </button>
          
          {/* Visual Progress Bar or Status */}
          <div className="progress-container">
            {isPosting ? (
              <div className="progress-bar-container">
                <div 
                  className="progress-bar-fill" 
                  style={{ width: `${(postingProgress.current / postingProgress.total) * 100}%` }}
                ></div>
              </div>
            ) : (
              <div className="status-bar">
                {statusMessage || "Ready"}
              </div>
            )}
          </div>
          
          <button
            onClick={() => {
                setPenalty([]);
                setSelectedOption("property");
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                setSelectedDate(`${year}-${month}`);
                setSearchBarangay("");
                setStatusMessage("Table cleared");
                setTimeout(() => setStatusMessage(""), 2000);
            }}
            className="footer-btn"
            disabled={isPosting}
          >
            Clear Table
          </button>
          
          <button 
            onClick={handlePost} 
            disabled={isPosting || penalty.length === 0}
            className="footer-btn"
          >
            {isPosting ? 'Posting...' : 'Post'}
          </button>
        </div>
      </div>

      {/* Property Search Modal */}
      {showPropertyModal && (
        <PropertySearchModal
          isOpen={showPropertyModal}
          onClose={() => setShowPropertyModal(false)}
          onSelectProperty={handlePropertySelect}
        />
      )}

      {/* Completion Modal */}
      {showCompletionModal && (
        <div className="modal-overlay" onClick={() => setShowCompletionModal(false)}>
          <div className="completion-modal" onClick={(e) => e.stopPropagation()}>
            <div className="completion-modal-header">
              <h3>PARTS</h3>
              <button className="close-btn" onClick={() => setShowCompletionModal(false)}>
                ×
              </button>
            </div>
            <div className="completion-modal-body">
              <p>Penalty posting completed.</p>
            </div>
            <div className="completion-modal-footer">
              <button onClick={() => setShowCompletionModal(false)}>OK</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PenaltyPosting;
