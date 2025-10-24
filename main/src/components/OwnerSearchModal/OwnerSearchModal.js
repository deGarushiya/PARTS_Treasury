import React, { useState, useEffect } from "react";
import PropertySearchModal from "../PropertySearchModal/PropertySearchModal";
import "./OwnerSearchModal.css";

const OwnerSearchModal = ({
  title = "Owner Search",
  apiEndpoint = "http://localhost:8000/api/ownersearch",
  mode = "payment",             // can be "payment", "manualdebit", etc.
  onClose = () => {},
  onSelectOwner = () => {}
}) => {
  const [filters, setFilters] = useState({
    lastName: "",
    firstName: "",
    localTin: "",
    birTin: "",
    fullname: "",
    address: ""
  });

  const [results, setResults] = useState([]);
  const [selectedOwner, setSelectedOwner] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [searchFromBeginning, setSearchFromBeginning] = useState(false);
  const [showPropertyModal, setShowPropertyModal] = useState(false);
  const [showNoResultsModal, setShowNoResultsModal] = useState(false);
  const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });


    const handleChange = (e) => {
      const { name, value } = e.target;
      setFilters((prev) => ({ ...prev, [name]: value }));
    };

  
    const handleSearch = async () => {
      setLoading(true);
      setError(null);

      try {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([key, val]) => {
          if (val) {
            if (["lastName", "firstName", "fullname"].includes(key) && searchFromBeginning)
              params.append(key, `${val}%`);
            else params.append(key, val);
          }
        });

        const res = await fetch(`${apiEndpoint}?${params.toString()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        setResults(Array.isArray(data) ? data : []);
        setSelectedOwner(null);

        if (!Array.isArray(data) || data.length === 0) {
          setShowNoResultsModal(true);
        }
      } catch (err) {
        console.error("Owner search failed:", err);
        setError("Failed to search. Please try again.");
      } finally {
        setLoading(false);
      }
    };


    const handleSelectOwner = () => {
      if (selectedOwner) {
        onSelectOwner(selectedOwner, mode);
        onClose();
      }
    };

    const handleSelectProperty = async (property) => {
      setShowPropertyModal(false);

      if (!property?.LOCAL_TIN) return;


      setFilters((prev) => ({ ...prev, localTin: property.LOCAL_TIN }));


      try {
        const res = await fetch(`${apiEndpoint}?localTin=${property.LOCAL_TIN}`);
        const data = await res.json();

        if (Array.isArray(data) && data.length > 0) {
          setResults(data);
        } else {
          setResults([]);
          setShowNoResultsModal(true);
        }
      } catch (err) {
        console.error("Error fetching owner data from property:", err);
        setResults([]);
      }
    };

    useEffect(() => {
      const onKey = (e) => e.key === "Escape" && onClose();
      document.addEventListener("keydown", onKey);
      return () => document.removeEventListener("keydown", onKey);
    }, [onClose]);

    const sortedResults = React.useMemo(() => {
        let sortableItems = [...results];
        if (sortConfig.key) {
          sortableItems.sort((a, b) => {
            const aValue = a[sortConfig.key]?.toString().toLowerCase() ?? "";
            const bValue = b[sortConfig.key]?.toString().toLowerCase() ?? "";
            if (aValue < bValue) return sortConfig.direction === "asc" ? -1 : 1;
            if (aValue > bValue) return sortConfig.direction === "asc" ? 1 : -1;
            return 0;
          });
        }
        return sortableItems;
      }, [results, sortConfig]);

      const handleSort = (key) => {
        setSortConfig((prev) => {
          if (prev.key === key) {
            return { key, direction: prev.direction === "asc" ? "desc" : "asc" };
          }
          return { key, direction: "asc" };
        });
      };


  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-title">
          <span>{title}</span>
          <button className="modal-close-btn" onClick={onClose}>×</button>
        </div>

        <div className="modal-body-content">
          {/*  FILTERS */}
          <div className="ownersearch-top">
          <div className="form-area">
            <div className="form-row two-col">
              <div>
                <label>Last Name:</label>
                <input name="lastName" value={filters.lastName} onChange={handleChange} />
              </div>
              <div>
                <label>Local TIN:</label>
                <input name="localTin" value={filters.localTin} onChange={handleChange} />
              </div>
            </div>

            <div className="form-row two-col">
              <div>
                <label>First Name:</label>
                <input name="firstName" value={filters.firstName} onChange={handleChange} />
              </div>
              <div>
                <label>BIR TIN No.:</label>
                <input name="birTin" value={filters.birTin} onChange={handleChange} />
              </div>
            </div>

            <div className="form-row full-width">
              <label>Fullname / Institution:</label>
              <input name="fullname" value={filters.fullname} onChange={handleChange} />
            </div>

            <div className="form-row full-width">
              <label>Address:</label>
              <input name="address" value={filters.address} onChange={handleChange} />
            </div>
          </div>

          {/*  SIDEBAR BUTTONS */}
          <div className="sidebar-controls">
            <button onClick={handleSearch} disabled={loading}>
              {loading ? "Searching..." : "Search"}
            </button>

            <button onClick={() => setShowPropertyModal(true)}>Search Property</button>

            <button onClick={handleSelectOwner} disabled={!selectedOwner}>
              Open
            </button>

            <div className="checkbox-row">
              <input
                type="checkbox"
                id="beginning"
                checked={searchFromBeginning}
                onChange={(e) => setSearchFromBeginning(e.target.checked)}
              />
              <label htmlFor="beginning">Search from beginning of name</label>
            </div>
          </div>
        </div>

        {error && <div className="error">{error}</div>}

        {/*  RESULTS TABLE */}
        <div className="results-table">
          <div className="table-scroll">
            <table>
              {/* <thead>
                <tr>
                  <th>Local TIN</th>
                  <th>Last Name</th>
                  <th>First Name</th>
                  <th>Middle Name</th>
                  <th>Owner Name</th>
                  <th>Owner Address</th>
                  <th>TIN No.</th>
                </tr>
              </thead> */}
              <thead>
                <tr>
                  <th onClick={() => handleSort('LOCAL_TIN')}
                      className={sortConfig.key === 'LOCAL_TIN' ? `sorted-${sortConfig.direction}` : ''}>
                    Local TIN
                  </th>
                  <th onClick={() => handleSort('LASTNAME')}
                      className={sortConfig.key === 'LASTNAME' ? `sorted-${sortConfig.direction}` : ''}>
                    Last Name
                  </th>
                  <th onClick={() => handleSort('FIRSTNAME')}
                      className={sortConfig.key === 'FIRSTNAME' ? `sorted-${sortConfig.direction}` : ''}>
                    First Name
                  </th>
                  <th onClick={() => handleSort('MI')}
                      className={sortConfig.key === 'MI' ? `sorted-${sortConfig.direction}` : ''}>
                    Middle Name
                  </th>
                  <th onClick={() => handleSort('OWNERNAME')}
                      className={sortConfig.key === 'OWNERNAME' ? `sorted-${sortConfig.direction}` : ''}>
                    Owner Name
                  </th>
                  <th onClick={() => handleSort('OWNERADDRESS')}
                      className={sortConfig.key === 'OWNERADDRESS' ? `sorted-${sortConfig.direction}` : ''}>
                    Owner Address
                  </th>
                  <th onClick={() => handleSort('TINNO')}
                      className={sortConfig.key === 'TINNO' ? `sorted-${sortConfig.direction}` : ''}>
                    TIN No.
                  </th>
                </tr>
              </thead>

              <tbody>
                {sortedResults.length > 0 ? (
                  sortedResults.map((row, idx) => (
                    <tr
                      key={idx}
                      onClick={() => setSelectedOwner(row)}
                      onDoubleClick={() => {
                        setSelectedOwner(row);
                        handleSelectOwner();
                      }}
                      className={selectedOwner?.LOCAL_TIN === row.LOCAL_TIN ? "selected-row" : ""}
                    >
                      <td title={row.LOCAL_TIN}>{row.LOCAL_TIN}</td>
                      <td title={row.LASTNAME}>{row.LASTNAME}</td>
                      <td title={row.FIRSTNAME}>{row.FIRSTNAME}</td>
                      <td title={row.MI}>{row.MI}</td>
                      <td title={row.OWNERNAME}>{row.OWNERNAME}</td>
                      <td title={row.OWNERADDRESS}>{row.OWNERADDRESS}</td>
                      <td title={row.TINNO}>{row.TINNO}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="7" style={{ textAlign: "center" }}>
                      No results
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* NO RESULTS MODAL */}
        {showNoResultsModal && (
          <div className="modal fade show d-block" tabIndex="-1" role="dialog" onClick={() => setShowNoResultsModal(false)}>
            <div className="modal-dialog modal-dialog-centered" role="document" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">No Results Found</h5>
                </div>
                <div className="modal-body">
                  <p>No search results found.</p>
                </div>
                <div className="modal-footer">
                  <button className="close-btn" onClick={() => setShowNoResultsModal(false)}>
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
        </div>

        {/*  PROPERTY SEARCH MODAL */}
        {showPropertyModal && (
          <PropertySearchModal
            onClose={() => setShowPropertyModal(false)}
            onSelectProperty={handleSelectProperty}
          />
        )}
      </div>
    </div>
  );
};

export default OwnerSearchModal;
