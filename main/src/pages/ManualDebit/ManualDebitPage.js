import React, { useState, useEffect } from "react";
import axios from "axios";
import "./ManualDebitPage.css";
import OwnerSearchModal from "../../components/OwnerSearchModal/OwnerSearchModal";
import { taxDueAPI } from "../../services/api";

const ManualDebitPage = () => {
  const [barangay, setBarangay] = useState("");
  const [barangays, setBarangays] = useState([]);
  const [selectedRowId, setSelectedRowId] = useState(null);
  const [showOwnerModal, setShowOwnerModal] = useState(false);

  // Owner data
  const [selectedOwner, setSelectedOwner] = useState(null);
  const [localTin, setLocalTin] = useState("");
  const [ownerName, setOwnerName] = useState("");

  // Properties and Assessments
  const [properties, setProperties] = useState([]);
  const [assessments, setAssessments] = useState([]);
  const [loadingAssessments, setLoadingAssessments] = useState(false);
  const [errorAssessments, setErrorAssessments] = useState(null);

  // 🔹 Fetch barangays from Laravel API
  useEffect(() => {
    axios.get("http://localhost:8000/api/barangays")
      .then(response => {
        setBarangays(response.data);
      })
      .catch(error => {
        console.error("Error fetching barangays:", error);
      });
  }, []);

  // 🔹 Fetch assessments when owner is selected
  useEffect(() => {
    if (!localTin) {
      setAssessments([]);
      return;
    }

    const fetchAssessments = async () => {
      setLoadingAssessments(true);
      setErrorAssessments(null);
      try {
        const response = await taxDueAPI.getAssessmentDetails(localTin);
        setAssessments(response.data);
      } catch (err) {
        console.error('Error fetching assessments:', err);
        setErrorAssessments('Failed to load assessments');
      } finally {
        setLoadingAssessments(false);
      }
    };

    fetchAssessments();
  }, [localTin]);


  return (
    <div className="manual-debit-container">
      <div className="top-section">
        {/* Left Panel */}
        <div className="left-panel card">
          <label>Barangay</label>
          <select
            value={barangay}
            onChange={(e) => setBarangay(e.target.value)}
          >
            <option value="">-- Choose --</option>
            {barangays.map((b) => (
              <option key={b.code} value={b.code}>
                {b.description}
              </option>
            ))}
          </select>


          <div className="mini-table">
            <table>
              <thead>
                <tr>
                  <th>PIN No</th>
                  <th>TD No</th>
                </tr>
              </thead>
              <tbody>
                {properties.map((p, i) => (
                  <tr key={i}>
                    <td>{p.pin}</td>
                    <td>{p.tdNo}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* <div className="total-rpu">
            <strong>Total RPUs:</strong> {properties.length}
          </div> */}
        </div>

        {/* Right Panel */}
        <div className="right-panel">
          {/* Owner Info */}
          <div className="card owner-header">
            <div>Owner Name: <strong>{localTin} {ownerName || '(No owner selected)'}</strong></div>
            <div>
              Properties: <label><input type="checkbox" /> Hide Cancelled</label>
            </div>
          </div>

          {/* Property Table */}
          <div className="card property-table">
            <table>
              <thead>
                <tr>
                  <th>PIN No</th>
                  <th>TD No</th>
                  <th>Previous TD No</th>
                  <th>Previous Ass Value</th>
                  <th>Property Kind</th>
                  <th>Street</th>
                  <th>Barangay</th>
                </tr>
              </thead>
              <tbody>
                {properties.map((p, i) => (
                  <tr key={i}>
                    <td>{p.pin}</td>
                    <td>{p.tdNo}</td>
                    <td>{p.prevTd}</td>
                    <td>{p.prevAss}</td>
                    <td>{p.kind}</td>
                    <td>{p.street}</td>
                    <td>{p.brgy}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Retrieve / Cancel + Assessment Report */}
          <div className="retrieve-section">
            <div className="button-row">
              <button>Retrieve</button>
              <button>Cancel</button>
            </div>
            <div className="assessment-report-title">
              <strong>ASSESSMENT REPORT</strong>
            </div>
          </div>

          {/* Action Panel */}
          <div className="action-panel">
            {/* Left Side - Radios + From/To + Buttons */}
            <div className="actions-left">
              <div className="radio-row">
                <label><input type="radio" name="debitType" /> New Debit</label>
                <label><input type="radio" name="debitType" /> Auto Debit</label>
              </div>

              <div className="range-buttons-row">
                <span>From:</span>
                <input type="text" className="small-input" />
                <span>To:</span>
                <input type="text" className="small-input" />

                <button>Insert</button>
                <button>Unpost Assessment</button>
                <button>Repost Assessment</button>
                <button>Delete Open Accounts</button>
              </div>
            </div>

            {/* Right Side - Assessment Report */}
            <div className="assessment-report">
              <div className="legend">
                <div><span className="legend-box open"></span> Open Account</div>
                <div><span className="legend-box installment"></span> Installment/Double Post</div>
                <div><span className="legend-box paid"></span> Paid Account</div>
              </div>
            </div>
          </div>


          {/* Assessment Table */}
          <div className="card assessment-table">
            {loadingAssessments && <p style={{ textAlign: 'center', padding: '20px' }}>Loading assessments...</p>}
            {errorAssessments && <p style={{ textAlign: 'center', padding: '20px', color: 'red' }}>{errorAssessments}</p>}
            
            {!loadingAssessments && !errorAssessments && assessments.length === 0 && (
              <p style={{ textAlign: 'center', padding: '20px', color: '#666' }}>
                No assessment records found. Please select an owner using the Search button.
              </p>
            )}

            {!loadingAssessments && !errorAssessments && assessments.length > 0 && (
              <table>
                <thead>
                  <tr>
                    <th>TD No</th>
                    <th>Year</th>
                    <th>PIN No</th>
                    <th>Land</th>
                    <th>Improvements</th>
                    <th>Total</th>
                    <th>Basic</th>
                    <th>SEF</th>
                    <th>Source</th>
                  </tr>
                </thead>
                <tbody>
                  {assessments.map((a, i) => (
                    <tr key={i} className={`status-${a.status}`}>
                      <td>{a.tdNo}</td>
                      <td>{a.year}</td>
                      <td>{a.pin}</td>
                      <td className="text-right">{a.land.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                      <td className="text-right">{a.improvements.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                      <td className="text-right"><strong>{a.total.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
                      <td className="text-right">{a.basic.toFixed(0)}</td>
                      <td className="text-right">{a.sef.toFixed(0)}</td>
                      <td className="text-center">{a.source}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="footer-section">
        <div>
          <strong>Total RPUs:</strong> {properties.length}
        </div>
        <div>
          <button onClick={() => setShowOwnerModal(true)}>Search</button>
          <button>Save/Post</button>
        </div>
      </div>

      {showOwnerModal && (
        <OwnerSearchModal
          title="Owner Search"
          apiEndpoint="http://localhost:8000/api/ownersearch"
          mode="manualdebit"
          onClose={() => setShowOwnerModal(false)}
          onSelectOwner={(owner) => {
            setSelectedOwner(owner);
            setLocalTin(owner.LOCAL_TIN);
            setOwnerName(owner.OWNERNAME);
            setSelectedRowId(owner);
            setShowOwnerModal(false);
          }}
        />
      )}

    </div>
  );
};

export default ManualDebitPage;
