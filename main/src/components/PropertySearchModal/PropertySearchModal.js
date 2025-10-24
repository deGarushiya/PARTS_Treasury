// src/components/PropertySearchModal/PropertySearchModal.jsx
import React, { useEffect, useState } from "react";
import "bootstrap/dist/css/bootstrap.min.css";
import "./PropertySearchModal.css";
import OwnerSearchModal from "../OwnerSearchModal/OwnerSearchModal";

const PropertySearchModal = ({
  isOpen = true,
  onClose = () => {},
  onSelectProperty = null, // Parent (like OwnerSearchModal) will receive selected data
}) => {
  const [properties, setProperties] = useState([]);
  const [searchType, setSearchType] = useState("TDNO");
  const [searchHistory, setSearchHistory] = useState([]);
  const [searchValue, setSearchValue] = useState("");
  const [selectedProperty, setSelectedProperty] = useState(null);
  const [hasSearched, setHasSearched] = useState(false);
  const [startWith, setStartWith] = useState(false);
  const [cancelled, setCancelled] = useState(false);
  const [sortConfig, setSortConfig] = useState({ key: null, direction: "asc" });
  const [showOwnerModal, setShowOwnerModal] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showNoResultModal, setShowNoResultModal] = useState(false);

  // Load default properties on mount
  useEffect(() => {
    fetch("http://localhost:8000/api/properties")
      .then((res) => res.json())
      .then((data) => setProperties(data))
      .catch((err) => console.error("Error fetching properties:", err));
  }, []);

  const handleSearch = () => {
    if (!searchValue.trim() && searchType !== "LOCAL_TIN") {
      setProperties([]);
      setHasSearched(false);
      return;
    }

    if (!searchHistory.includes(searchValue)) {
      setSearchHistory((prev) => [searchValue, ...prev].slice(0, 20));
    }

    if (searchType === "LOCAL_TIN") {
      setShowOwnerModal(true);
      return;
    }

    setLoading(true);
    fetch(
      `http://localhost:8000/api/properties/search?searchBy=${searchType}&value=${searchValue}&startWith=${startWith}&cancelled=${cancelled}`
    )
      .then((res) => res.json())
      .then((data) => {
        if (Array.isArray(data) && data.length > 0) {
          setProperties(data);
          setHasSearched(true);
        } else {
          setProperties([]);
          setHasSearched(true);
          setShowNoResultModal(true);
        }
      })
      .catch((err) => {
        console.error("Search error:", err);
        setShowNoResultModal(true);
      })
      .finally(() => setLoading(false));
  };

  const handleSelectOwner = (owner) => {
    setSearchValue(owner.LOCAL_TIN);
    setShowOwnerModal(false);
  };

  const handleClear = () => {
    setSearchValue("");
    fetch("http://localhost:8000/api/properties")
      .then((res) => res.json())
      .then((data) => setProperties(data));
    setHasSearched(false);
    setSelectedProperty(null);
  };

  const handleSort = (key) => {
    setSortConfig((prev) => {
      if (prev.key === key && prev.direction === "asc") {
        return { key, direction: "desc" };
      }
      return { key, direction: "asc" };
    });
  };

  const sortedProperties = [...properties].sort((a, b) => {
    if (!sortConfig.key) return 0;
    const valueA = a[sortConfig.key] ?? "";
    const valueB = b[sortConfig.key] ?? "";
    if (valueA < valueB) return sortConfig.direction === "asc" ? -1 : 1;
    if (valueA > valueB) return sortConfig.direction === "asc" ? 1 : -1;
    return 0;
  });

  if (!isOpen) return null;

  return (
    <>
      <div className="property-modal">
        <div className="modal-overlay">
          <div className="modal-content">
            <div className="modal-title">
              <span>Property Search</span>
              <button className="modal-close-btn" onClick={onClose}>×</button>
            </div>

            <div className="modal-body-content">
              {/* Search Controls */}
              <div className="ownersearch-top">
            <div className="form-area">
              <div className="container">
                <div className="row align-items-start">
                  <div className="col-4 text">Search By</div>
                  <div className="col">
                    <div className="form-row">
                      <div className="checkbox-row">
                        <input
                          type="checkbox"
                          id="startWith"
                          checked={startWith}
                          onChange={(e) => setStartWith(e.target.checked)}
                        />
                        <label htmlFor="startWith">Start with</label>
                      </div>
                      <div className="checkbox-row">
                        <input
                          type="checkbox"
                          id="cancelled"
                          checked={cancelled}
                          onChange={(e) => setCancelled(e.target.checked)}
                        />
                        <label htmlFor="cancelled">Cancelled Properties</label>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="row align-items-start">
                  <div className="col-4">
                    <select
                      className="dropdown"
                      value={searchType}
                      onChange={(e) => {
                        const value = e.target.value;
                        setSearchType(value);
                        if (value === "LOCAL_TIN") {
                          setShowOwnerModal(true);
                        }
                      }}
                    >
                      <option value="CADASTRALLOTNO">Cadastral Lot No.</option>
                      <option value="LOCAL_TIN">Local TIN</option>
                      <option value="CERTIFICATETITLENO">OCT/TCT/CLOA No.</option>
                      <option value="PINNO">PIN No.</option>
                      <option value="TDNO">TD No.</option>
                      <option value="PREVTDNO">Previous TD No.</option>
                    </select>
                  </div>

                  <div className="col-4 sidebar-controls">
                    <input
                      className="dropdown"
                      list="search-history"
                      value={searchValue}
                      onChange={(e) => setSearchValue(e.target.value)}
                    />
                    <datalist id="search-history">
                      {searchHistory.map((item, idx) => (
                        <option key={idx} value={item} />
                      ))}
                    </datalist>
                  </div>

                  <div className="col-4 sidebar-controls">
                    <button
                      className={`buttons ${loading ? "disabled-btn" : ""}`}
                      onClick={handleSearch}
                      disabled={loading}
                    >
                      {loading ? "Searching..." : "Search"}
                    </button>
                    <button className="buttons" onClick={handleClear}>Clear</button>
                    <button
                      className="buttons"
                      onClick={() => {
                        if (!selectedProperty) {
                          alert("No Data Selected");
                          return;
                        }
                        onSelectProperty?.(selectedProperty);
                        onClose();
                      }}
                    >
                      Return
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Results Table */}
          <div className="results-table">
            <div className="table-scroll">
              <table className="modeltable">
                <thead>
                  <tr>
                    <th onClick={() => handleSort("LOCAL_TIN")}>Local TIN</th>
                    <th onClick={() => handleSort("OWNERNAME")}>Owner Name</th>
                    <th onClick={() => handleSort("TDNO")}>TD No.</th>
                    <th onClick={() => handleSort("PINNO")}>PIN No.</th>
                    <th onClick={() => handleSort("CADASTRALLOTNO")}>Cadastral Lot</th>
                    <th onClick={() => handleSort("CERTIFICATETITLENO")}>Title/OCT</th>
                    <th onClick={() => handleSort("barangay")}>Barangay</th>
                    <th onClick={() => handleSort("USERID")}>Encoder</th>
                    <th onClick={() => handleSort("TRANSDATE")}>Entry Date</th>
                  </tr>
                </thead>
                <tbody>
                  {hasSearched ? (
                    sortedProperties.length > 0 ? (
                      sortedProperties.map((property) => (
                        <tr
                          key={property.PROP_ID}
                          onClick={() => setSelectedProperty(property)}
                          onDoubleClick={() => {
                            onSelectProperty?.(property);
                            onClose();
                          }}
                          className={
                            selectedProperty?.PROP_ID === property.PROP_ID
                              ? "selected-row"
                              : ""
                          }
                        >
                          <td title={property.LOCAL_TIN}>{property.LOCAL_TIN}</td>
                          <td title={property.OWNERNAME}>{property.OWNERNAME}</td>
                          <td title={property.TDNO}>{property.TDNO}</td>
                          <td title={property.PINNO}>{property.PINNO}</td>
                          <td title={property.CADASTRALLOTNO}>{property.CADASTRALLOTNO}</td>
                          <td title={property.CERTIFICATETITLENO}>{property.CERTIFICATETITLENO}</td>
                          <td title={property.barangay}>{property.barangay}</td>
                          <td title={property.USERID}>{property.USERID}</td>
                          <td title={property.TRANSDATE}>{property.TRANSDATE}</td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="9" style={{ textAlign: "center" }}>
                          No results found
                        </td>
                      </tr>
                    )
                  ) : (
                    [...Array(5)].map((_, idx) => (
                      <tr key={idx}>
                        {Array.from({ length: 9 }).map((_, colIdx) => (
                          <td key={colIdx}>&nbsp;</td>
                        ))}
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Annotation + Memoranda */}
          <div className="info">
            <label className="textbox-label col-2">Annotation:</label>
            <textarea
              className="it col-9 annotation-box"
              disabled
              value={selectedProperty?.ANNOTATION || ""}
            />
          </div>
          <div className="info">
            <label className="textbox-label col-2">Memoranda:</label>
            <textarea
              className="it col-9 memoranda-box"
              disabled
              value={selectedProperty?.MEMORANDA || ""}
            />
          </div>
            </div>
        </div>

        {/* No Results Modal */}
        {showNoResultModal && (
          <div className="modal fade show d-block" tabIndex="-1" role="dialog">
            <div
              className="modal-dialog modal-dialog-centered"
              role="document"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">No Results Found</h5>
                </div>
                <div className="modal-body">
                  <p>No properties matched your search.</p>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    className="close-button"
                    onClick={() => setShowNoResultModal(false)}
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
        </div>
      </div>

      {/* Linked Owner Modal - Rendered outside PropertySearchModal */}
      {showOwnerModal && (
        <OwnerSearchModal
          onClose={() => setShowOwnerModal(false)}
          onSelectOwner={handleSelectOwner}
        />
      )}
    </>
  );
};

export default PropertySearchModal;
