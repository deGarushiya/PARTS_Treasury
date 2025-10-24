import React from "react";
import { NavLink } from "react-router-dom";
import "./Navbar.css";

const Navbar = () => {
  return (
    <nav className="navbar">
      <ul>
        <li>
          <NavLink
            to="/"
            end
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Payment Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/manual-debit"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Manual Debit
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/assessment-posting"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Assessment Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/penalty-posting"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Penalty Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/reports"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Reports
          </NavLink>
        </li>
      </ul>
    </nav>
  );
};

export default Navbar;
