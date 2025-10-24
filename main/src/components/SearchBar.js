import React from 'react';

export default function SearchBar({ setSearch }) {
  return (
    <input
      type="text"
      placeholder="Search by TIN, Name, Receipt, Date..."
      onChange={(e) => setSearch(e.target.value)}
      style={{ width: '300px', marginBottom: '10px' }}
    />
  );
}
